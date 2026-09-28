<?php

namespace VasskisgepTheme;

/**
 * A jóváhagyott rekordokból WooCommerce termékek (a régi azonosító alapján frissít, nem duplikál).
 */
class MigrationImporter {
    const TIME_BUDGET = 12;

    /** Egy adag import (időkorláttal). */
    static function step(){
        $start = microtime(true);
        $ids = Migration::ids('ready', 25);
        $done = 0;
        $errors = [];
        $redirects = Migration::redirects();
        foreach($ids as $id){
            $result = self::import_one($id);
            if (is_wp_error($result)){
                $errors[] = get_post_field('post_title', $id).': '.$result->get_error_message();
                $suggestion = Migration::suggestion($id);
                $suggestion['note'] = 'Import hiba: '.$result->get_error_message();
                Migration::set_suggestion($id, $suggestion, 'review');
            } else {
                $data = Migration::data($id);
                foreach(array_unique(array_merge((array)($data['all_paths'] ?? []), [ $data['path_url'] ?? '' ])) as $old_path){
                    if ($old_path !== '') $redirects['products'][Migration::normalize_path($old_path)] = (int)$result;
                }
                $done++;
            }
            if (microtime(true) - $start > self::TIME_BUDGET) break;
        }
        update_option(Migration::REDIRECTS, $redirects, false);
        $left = count(Migration::ids('ready', 1));
        if (!$left) self::category_redirects();
        wc_delete_product_transients();
        delete_transient('wc_term_counts');
        MegaMenu::flush();
        $counts = Migration::counts();
        return [
            'done' => !$left,
            'message' => sprintf('%d termék importálva (összesen %d, hátravan %d)', $done, $counts['imported'], $counts['ready']).($errors ? "\n".implode("\n", $errors) : '')
        ];
    }

    /** Meglévő termék: a régi azonosító, különben az egyező cikkszám alapján. */
    private static function existing(array $data){
        $found = get_posts([ 'post_type' => 'product', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_vk_old_id', 'meta_value' => (int)$data['id'] ]);
        if ($found) return (int)$found[0];
        return 0;
    }

    static function import_one($id){
        $data = Migration::data($id);
        $suggestion = Migration::suggestion($id);
        if (empty($data['name']) || empty($suggestion['term'])) return new \WP_Error('data', 'hiányzó adat vagy kategória');
        $settings = Migration::settings();

        $product_id = self::existing($data);
        $product = $product_id ? wc_get_product($product_id) : new \WC_Product_Simple();
        if (!$product) $product = new \WC_Product_Simple();

        $product->set_name($data['name']);
        if (!$product_id && !empty($data['path_url'])) $product->set_slug(sanitize_title(basename($data['path_url'])));
        $product->set_status($settings['status'] === 'draft' ? 'draft' : 'publish');

        // Ár: a régi „alapár” a bruttó listaár, a meta ár az aktuális (akciós) ár.
        $price = (float)($data['price'] ?? 0);
        $regular = (float)($data['regular'] ?? 0);
        if ($regular > $price && $price > 0){
            $product->set_regular_price((string)round($regular));
            $product->set_sale_price((string)round($price));
        } else {
            $product->set_regular_price($price > 0 ? (string)round($price) : '');
            $product->set_sale_price('');
        }

        // Készlet: a régi boltban a „nincs készleten” = „Visszaigazolástól 1-5 munkanap” (rendelhető).
        $product->set_manage_stock(false);
        $product->set_stock_status(substr((string)($data['availability'] ?? ''), -7) === 'InStock' ? 'instock' : 'onbackorder');

        $sku = trim((string)($data['sku'] ?? ''));
        if ($sku !== ''){
            $owner = wc_get_product_id_by_sku($sku);
            if (!$owner || $owner === $product_id) $product->set_sku($sku);
        }

        $product->set_category_ids([ (int)$suggestion['term'] ]);

        $images = $settings['images'];
        $description = self::clean_description((string)($data['description'] ?? ''), $images !== 'none' ? $product_id : false);
        $product->set_description($description);
        if (!$product_id || $product->get_short_description() === '') $product->set_short_description(self::short_description($description));

        [$attributes, $terms] = self::attributes(self::spec_fields($data));
        $product->set_attributes($attributes);

        $product->update_meta_data('_vk_old_id', (int)$data['id']);
        $product->update_meta_data('_vk_old_url', esc_url_raw($data['url'] ?? ''));
        $product_id = $product->save();
        if (!$product_id) return new \WP_Error('save', 'a termék nem menthető');

        foreach($terms as $taxonomy => $term_id) wp_set_object_terms($product_id, [ $term_id ], $taxonomy);
        $brand = self::brand($data);
        if ($brand) wp_set_object_terms($product_id, [ $brand ], 'product_brand');

        // Képek (a leírásba ágyazottak a clean_description-ben) és dokumentumok.
        if ($images !== 'none' && !empty($data['images'])){
            $urls = $images === 'main' ? array_slice($data['images'], 0, 1) : $data['images'];
            $attachments = [];
            foreach($urls as $url){
                $attachment = self::sideload($url, $product_id, $data['name']);
                if ($attachment) $attachments[] = $attachment;
            }
            if ($attachments){
                $product = wc_get_product($product_id);
                $product->set_image_id(array_shift($attachments));
                $product->set_gallery_image_ids($attachments);
                $product->save();
            }
        }
        if ($images !== 'none' && !empty($data['documents'])){
            $docs = [];
            foreach($data['documents'] as $url){
                $doc = self::sideload($url, $product_id);
                if ($doc) $docs[] = $doc;
            }
            if ($docs) update_post_meta($product_id, '_vk_documents', implode(',', $docs));
        }

        update_post_meta($id, '_vk_mig_product', $product_id);
        update_post_meta($id, '_vk_mig_status', 'imported');
        return $product_id;
    }

    /* ---------- Tartalom ---------- */

    static function clean_description($html, $parent = false){
        if ($html === '') return '';
        $base = MigrationSource::base();
        $html = preg_replace('/\s(style|class|id|align|width|height|border|cellpadding|cellspacing)="[^"]*"/i', '', $html);
        $html = preg_replace_callback('/\s(src|href)="(\/[^"]*)"/i', function($m) use ($base){ return ' '.$m[1].'="'.$base.$m[2].'"'; }, $html);
        if ($parent !== false){
            // Beágyazott képek a médiatárba (a régi oldal megszűnése után is megmaradnak).
            $html = preg_replace_callback('/<img\s[^>]*src="([^"]+)"[^>]*>/i', function($m) use ($parent){
                $attachment = self::sideload(html_entity_decode($m[1]), (int)$parent);
                return $attachment ? wp_get_attachment_image($attachment, 'large') : $m[0];
            }, $html);
        }
        $html = preg_replace('/<p>(\s|&nbsp;)*<\/p>/u', '', $html);
        return trim(wp_kses_post($html));
    }

    static function short_description($html){
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8')));
        if ($text === '') return '';
        if (mb_strlen($text) <= 200) return $text;
        $cut = mb_substr($text, 0, 200);
        $sentence = mb_strrpos($cut, '. ');
        return $sentence > 80 ? mb_substr($cut, 0, $sentence + 1) : rtrim(mb_substr($cut, 0, mb_strrpos($cut, ' '))).'…';
    }

    /** Műszaki adatok: a szűrhető tulajdonságok (pa_*) és a többi helyi tulajdonságként. */
    /**
     * A régi termék műszaki adatai, kiegészítve a leírásból kinyert szűrőértékekkel ott, ahol a régi
     * bolt külön mezőben nem adta meg (pl. „Súly 18 kg”, „Munkaszélesség: 90 cm”, „Teljesítmény 1 kW”).
     * Csak mértékegységgel együtt szereplő értéket vesz át; a teljesítményfelvételt nem, a kW-ot LE-re váltja.
     */
    static function spec_fields(array $data){
        $fields = (array)($data['fields'] ?? []);
        $text = html_entity_decode(wp_strip_all_tags(str_replace('<', ' <', (string)($data['description'] ?? ''))), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $text));
        if ($text === '') return $fields;
        $n = '(\d+(?:[.,]\d+)?)';
        $number = function($value){ return (float)str_replace(',', '.', $value); };
        $format = function($value){ return str_replace('.', ',', (string)round($value, 1)); };
        $patterns = [
            'Lökettérfogat cm3' => [ '/(?<!\w)(?:lökettérfogat|hengerűrtartalom|hengertérfogat)\w*\s*[:\-–]?\s*(?:kb\.?\s*)?'.$n.'\s*(?:cm3|cm³|ccm|cc)(?!\w)/iu', function($m) use ($number, $format){ return $format($number($m[1])); } ],
            'Teljesítmény LE' => [ '/(?<!\w)teljesítmény(?!felvétel)\w*\s*[:\-–]?\s*(?:kb\.?\s*|max\.?\s*)?'.$n.'\s*(LE|kW|HP)(?!\w)/iu', function($m) use ($number, $format){ return $format(strcasecmp($m[2], 'kW') === 0 ? $number($m[1]) * 1.36 : $number($m[1])); } ],
            'Tömeg' => [ '/(?<!\w)(?:tömeg|súly)\w*\s*(?:\([^)]{0,30}\))?\s*[:\-–]?\s*(?:kb\.?\s*|körülbelül\s*|max\.?\s*)?'.$n.'\s*kg(?!\w)/iu', function($m) use ($number, $format){ return $format($number($m[1])); } ],
            'Vágásszélesség' => [ '/(?<!\w)(?:vágásszélesség|vágási szélesség|munkaszélesség)\w*\s*[:\-–]?\s*'.$n.'\s*(cm|mm)(?!\w)/iu', function($m) use ($number, $format){ return $format(strcasecmp($m[2], 'mm') === 0 ? $number($m[1]) / 10 : $number($m[1])); } ],
        ];
        foreach($patterns as $label => [$pattern, $value]){
            if (isset($fields[$label]) && trim((string)$fields[$label]) !== '') continue;
            if (preg_match($pattern, $text, $m)) $fields[$label] = $value($m);
        }
        return $fields;
    }

    static function attributes(array $fields){
        $map = MigrationClassifier::map();
        $attributes = [];
        $terms = [];
        $position = 0;
        foreach($fields as $label => $value){
            $value = trim((string)$value);
            if ($value === '' || stripos($value, 'Válasszon') === 0) continue;
            $value = preg_replace('/(\d)\.(\d)/', '$1,$2', $value);
            $attribute = new \WC_Product_Attribute();
            if (!empty($map['fields'][$label]) && wc_attribute_taxonomy_id_by_name($map['fields'][$label])){
                $slug = $map['fields'][$label];
                $taxonomy = wc_attribute_taxonomy_name($slug);
                if (!taxonomy_exists($taxonomy)) register_taxonomy($taxonomy, 'product', [ 'hierarchical' => false, 'public' => false ]);
                $term = get_term_by('name', $value, $taxonomy);
                if (!$term){
                    $result = wp_insert_term($value, $taxonomy, [ 'slug' => sanitize_title(str_replace([',', '.'], '-', $value)) ]);
                    if (is_wp_error($result)) continue;
                    $term = get_term($result['term_id'], $taxonomy);
                }
                $attribute->set_id(wc_attribute_taxonomy_id_by_name($slug));
                $attribute->set_name($taxonomy);
                $attribute->set_options([ (int)$term->term_id ]);
                $terms[$taxonomy] = (int)$term->term_id;
            } else {
                $unit = $map['local_fields'][$label] ?? '';
                $attribute->set_id(0);
                $attribute->set_name($label);
                $attribute->set_options([ $value.($unit !== '' && !preg_match('/[a-zA-Z³]$/u', $value) ? ' '.$unit : '') ]);
            }
            $attribute->set_position($position++);
            $attribute->set_visible(true);
            $attribute->set_variation(false);
            $attributes[] = $attribute;
        }
        return [ $attributes, $terms ];
    }

    /** Márka: a régi márkanév (vagy a régi főkategória) alapján; hiányzó márka létrehozása logóval. */
    static function brand(array $data){
        if (!taxonomy_exists('product_brand')) return 0;
        $brands = MigrationClassifier::map()['brands'];
        $name = $brands[$data['brand'] ?? ''] ?? '';
        if ($name === '' && !empty($data['path'][0])){
            foreach($brands as $old => $new){
                if (mb_strtolower(trim($old)) === mb_strtolower(trim($data['path'][0])) || stripos($data['path'][0], trim($old)) === 0) $name = $new;
            }
        }
        if ($name === '') return 0;
        $term = get_term_by('name', $name, 'product_brand');
        if (!$term){
            $result = wp_insert_term($name, 'product_brand', [ 'slug' => sanitize_title($name) ]);
            if (is_wp_error($result)) return 0;
            $term = get_term($result['term_id'], 'product_brand');
            $logo = Setup::media('marka-'.sanitize_title($name).'.png');
            if ($logo) update_term_meta($term->term_id, 'thumbnail_id', $logo);
        }
        return (int)$term->term_id;
    }

    /* ---------- Fájlok ---------- */

    /** Kép vagy dokumentum letöltése a médiatárba (egyszer: a forrás címe alapján). */
    static function sideload($url, $parent = 0, $title = ''){
        $url = trim($url);
        if ($url === '') return 0;
        $existing = get_posts([ 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_vk_source_url', 'meta_value' => $url ]);
        if ($existing) return (int)$existing[0];

        require_once(ABSPATH.'wp-admin/includes/file.php');
        require_once(ABSPATH.'wp-admin/includes/media.php');
        require_once(ABSPATH.'wp-admin/includes/image.php');

        $parts = wp_parse_url($url);
        if (empty($parts['host'])) return 0;
        $path = implode('/', array_map('rawurlencode', explode('/', rawurldecode($parts['path'] ?? ''))));
        $download = $parts['scheme'].'://'.$parts['host'].$path;
        $tmp = download_url($download, 60);
        if (is_wp_error($tmp)) return 0;
        $file = [ 'name' => sanitize_file_name(remove_accents(rawurldecode(basename($parts['path'] ?? 'fajl')))), 'tmp_name' => $tmp ];
        $attachment = media_handle_sideload($file, (int)$parent, $title !== '' ? $title : null);
        if (is_wp_error($attachment)){
            @unlink($tmp);
            return 0;
        }
        update_post_meta($attachment, '_vk_source_url', $url);
        if ($title !== '') update_post_meta($attachment, '_wp_attachment_image_alt', $title);
        return (int)$attachment;
    }

    /* ---------- Átirányítás a régi kategóriacímekről ---------- */

    /**
     * Egy régi kategória termékeinek új kategóriáiból a cél: a gépek (ha vannak, a tartozékok és
     * alkatrészek nélkül); ha egy kategória dominál (70%+), az, különben a legközelebbi közös szülő.
     */
    static function best_category(array $votes){
        $top = function($term_id){
            $ancestors = get_ancestors($term_id, 'product_cat');
            $root = $ancestors ? end($ancestors) : $term_id;
            $term = get_term($root, 'product_cat');
            return $term && !is_wp_error($term) ? $term->name : '';
        };
        $machines = array_filter($votes, function($n, $id) use ($top){ return !in_array($top($id), [ 'Tartozékok', 'Alkatrészek és karbantartás' ], true); }, ARRAY_FILTER_USE_BOTH);
        if (!empty($machines)) $votes = $machines;
        arsort($votes);
        $first = (int)array_key_first($votes);
        if (reset($votes) / array_sum($votes) >= 0.7 || count($votes) === 1) return $first;
        $common = null;
        foreach(array_keys($votes) as $id){
            $chain = array_merge([ (int)$id ], array_map('intval', get_ancestors((int)$id, 'product_cat')));
            $common = $common === null ? $chain : array_values(array_intersect($common, $chain));
        }
        return !empty($common) ? (int)$common[0] : $first;
    }


    /**
     * A régi kategóriaútvonalak (a termékcímek szülő útvonalai) → az ott lévő termékek leggyakoribb új
     * kategóriája; a régi márka-főkategória (/termekek/stihl) → a márka oldala.
     */
    static function category_redirects(){
        $votes = [];
        $brands = [];
        foreach(Migration::ids('imported') as $id){
            $data = Migration::data($id);
            $term = (int)(Migration::suggestion($id)['term'] ?? 0);
            $path = Migration::normalize_path($data['path_url'] ?? '');
            if ($path === '' || !$term) continue;
            $segments = explode('/', $path);
            array_pop($segments);
            for ($i = count($segments); $i >= 2; $i--){
                $prefix = implode('/', array_slice($segments, 0, $i));
                if ($i === 2){
                    $brand = self::brand($data);
                    if ($brand) $brands[$prefix] = $brand;
                    continue;
                }
                $votes[$prefix][$term] = ($votes[$prefix][$term] ?? 0) + 1;
            }
        }
        $map = Migration::redirects();
        foreach($votes as $prefix => $terms) $map['categories'][$prefix] = self::best_category($terms);
        foreach($brands as $prefix => $brand) $map['categories'][$prefix] = $brand;
        update_option(Migration::REDIRECTS, $map, false);
    }
}
