<?php

namespace VasskisgepTheme;

/**
 * Az átköltöztetés (a régi oldalak beolvasása, 2026-10) után a régi boltba felvett termékek pótlása a régi adatbázisból
 * (2026-11-06, Áron: „az új terméket nem tudod áthozni?”). A régi oldal már nem érhető el, de a régi Joomla-táblák
 * (MigrationData forrása) megvannak: a hiányzó, közzétett régi termékekből a megszokott módon (Migration-rekord →
 * besorolás → MigrationImporter) WooCommerce termék lesz, a régi címe rá irányít.
 *
 * Hiányzó: a legutóbb átvett régi termék után felvett, közzétett régi termék, amelynek sem a régi azonosítója (_vk_old_id), sem a cikkszáma nincs meg; a méret-
 * változat (szülője van) kimarad, ha a szülő megvan. Ár: a régi nettó ár + a 27% áfa (vagy a felülírt végár).
 * Kép: a régi fájl a szerveren (ha a régi oldal fájljai valahol még megvannak), különben a régi címen próbálja.
 */
class MigrationNewProducts {
    const STATE = 'vk_mig_newprod2';
    const VAT = 27;

    /** A hiányzó régi termékek: [ [ id, name, sku, created, parent ], … ] (legfeljebb $limit). */
    static function missing($limit = 200){
        global $wpdb;
        if (!MigrationData::has('virtuemart_products')) return [];
        $lang = MigrationData::lang_table('virtuemart_products');
        $rows = $wpdb->get_results('SELECT p.virtuemart_product_id AS id, p.product_parent_id AS parent, p.product_sku AS sku, p.created_on AS created'
            .($lang !== '' ? ', l.product_name AS name FROM '.MigrationData::t('virtuemart_products').' p LEFT JOIN '.MigrationData::t($lang).' l ON l.virtuemart_product_id = p.virtuemart_product_id' : ', \'\' AS name FROM '.MigrationData::t('virtuemart_products').' p')
            .' WHERE p.published = 1 ORDER BY p.virtuemart_product_id', ARRAY_A);
        // Megvan: közzétett termék ezzel a régi azonosítóval (az átköltöztetési rekord, piszkozat vagy kuka nem számít).
        $mapped = [];
        foreach($wpdb->get_col("SELECT pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_vk_old_id' AND p.post_type = 'product' AND p.post_status = 'publish'") as $old) $mapped[(int)$old] = true;
        // Csak a legutóbb átvett régi termék után felvettek: a régebbi, de hiányzó termékeket (az átköltöztetéskor
        // kihagyva, az új boltból törölve) nem hozza vissza.
        $cutoff = '';
        foreach((array)$rows as $row) if (isset($mapped[(int)$row['id']]) && (string)$row['created'] > $cutoff) $cutoff = (string)$row['created'];
        $out = [];
        foreach((array)$rows as $row){
            $id = (int)$row['id'];
            if (isset($mapped[$id]) || (string)$row['created'] <= $cutoff) continue;
            if ((int)$row['parent'] && isset($mapped[(int)$row['parent']])) continue;
            $sku = trim((string)$row['sku']);
            if ($sku !== '' && wc_get_product_id_by_sku($sku)) continue;
            $out[] = [ 'id' => $id, 'name' => trim(wp_strip_all_tags((string)$row['name'])), 'sku' => $sku, 'created' => (string)$row['created'], 'parent' => (int)$row['parent'] ];
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /** Hibakereséshez (állapotlap, ?q=): a régi termékek név / cikkszám szerint, és hogy miért nem hiányzónak számítanak. */
    static function lookup($q){
        global $wpdb;
        if ($q === '' || !MigrationData::has('virtuemart_products')) return [];
        $lang = MigrationData::lang_table('virtuemart_products');
        if ($lang === '') return [];
        $like = '%'.$wpdb->esc_like($q).'%';
        $rows = $wpdb->get_results($wpdb->prepare('SELECT p.virtuemart_product_id AS id, p.product_parent_id AS parent, p.product_sku AS sku, p.published, p.created_on AS created, l.product_name AS name FROM '.MigrationData::t('virtuemart_products').' p LEFT JOIN '.MigrationData::t($lang).' l ON l.virtuemart_product_id = p.virtuemart_product_id WHERE l.product_name LIKE %s OR p.product_sku LIKE %s LIMIT 20', $like, $like), ARRAY_A);
        $mapped_max = (string)$wpdb->get_var('SELECT MAX(p.created_on) FROM '.MigrationData::t('virtuemart_products').' p JOIN '.$wpdb->postmeta." pm ON pm.meta_key = '_vk_old_id' AND pm.meta_value = p.virtuemart_product_id JOIN {$wpdb->posts} wp ON wp.ID = pm.post_id AND wp.post_type = 'product' AND wp.post_status = 'publish'");
        $rows = (array)$rows;
        foreach($rows as &$row){
            $row['mapped_to'] = (string)$wpdb->get_var($wpdb->prepare("SELECT CONCAT(p.ID, ' ', p.post_type, ' ', p.post_status) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_vk_old_id' AND pm.meta_value = %s ORDER BY (p.post_type = 'product') DESC LIMIT 1", (string)$row['id']));
            $row['sku_owner'] = trim((string)$row['sku']) !== '' ? (int)wc_get_product_id_by_sku(trim((string)$row['sku'])) : 0;
            $row['cutoff'] = $mapped_max;
        }
        unset($row);
        return $rows;
    }

    /** A régi kategória útvonala a gyökértől: [ [ név, slug ], … ]. */
    private static function category_chain($category_id){
        global $wpdb;
        $lang = MigrationData::lang_table('virtuemart_categories');
        $chain = [];
        for ($guard = 0; $category_id && $guard < 10; $guard++){
            $row = $lang !== '' ? $wpdb->get_row($wpdb->prepare('SELECT category_name AS name, slug FROM '.MigrationData::t($lang).' WHERE virtuemart_category_id = %d', $category_id), ARRAY_A) : null;
            if (!$row) break;
            array_unshift($chain, [ trim(html_entity_decode((string)$row['name'], ENT_QUOTES, 'UTF-8')), (string)$row['slug'] ]);
            $category_id = MigrationData::has('virtuemart_category_categories') ? (int)$wpdb->get_var($wpdb->prepare('SELECT category_parent_id FROM '.MigrationData::t('virtuemart_category_categories').' WHERE category_child_id = %d LIMIT 1', $category_id)) : 0;
        }
        return $chain;
    }

    /** A régi termék adatai a beolvasó (MigrationSource::parse) alakjában. */
    static function data($old_id){
        global $wpdb;
        $p = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.MigrationData::t('virtuemart_products').' WHERE virtuemart_product_id = %d', $old_id), ARRAY_A);
        if (!$p) return null;
        $lang = MigrationData::lang_table('virtuemart_products');
        $l = $lang !== '' ? (array)$wpdb->get_row($wpdb->prepare('SELECT * FROM '.MigrationData::t($lang).' WHERE virtuemart_product_id = %d', $old_id), ARRAY_A) : [];

        // Ár: nettó + áfa; „override” 1: a felülírt végár, -1: a felülírt adózás előtti ár.
        $price = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.MigrationData::t('virtuemart_product_prices').' WHERE virtuemart_product_id = %d ORDER BY virtuemart_product_price_id LIMIT 1', $old_id), ARRAY_A);
        $gross = $regular = 0.0;
        if ($price){
            $net = (float)$price['product_price'];
            $regular = round($net * (1 + self::VAT / 100));
            $override = (int)($price['override'] ?? 0);
            $over = (float)($price['product_override_price'] ?? 0);
            $gross = $override === 1 && $over > 0 ? round($over) : ($override === -1 && $over > 0 ? round($over * (1 + self::VAT / 100)) : $regular);
        }

        // Kategória (a legmélyebb), márka.
        $cats = MigrationData::has('virtuemart_product_categories') ? $wpdb->get_col($wpdb->prepare('SELECT virtuemart_category_id FROM '.MigrationData::t('virtuemart_product_categories').' WHERE virtuemart_product_id = %d ORDER BY ordering', $old_id)) : [];
        $chain = [];
        foreach($cats as $cat){
            $c = self::category_chain((int)$cat);
            if (count($c) > count($chain)) $chain = $c;
        }
        $brand = '';
        $mlang = MigrationData::lang_table('virtuemart_manufacturers');
        if ($mlang !== '' && MigrationData::has('virtuemart_product_manufacturers')){
            $brand = (string)$wpdb->get_var($wpdb->prepare('SELECT m.mf_name FROM '.MigrationData::t('virtuemart_product_manufacturers').' pm JOIN '.MigrationData::t($mlang).' m ON m.virtuemart_manufacturer_id = pm.virtuemart_manufacturer_id WHERE pm.virtuemart_product_id = %d LIMIT 1', $old_id));
        }

        // Képek (a régi fájl elérési útja).
        $images = [];
        if (MigrationData::has('virtuemart_product_medias') && MigrationData::has('virtuemart_medias')){
            $images = $wpdb->get_col($wpdb->prepare('SELECT m.file_url FROM '.MigrationData::t('virtuemart_product_medias').' pm JOIN '.MigrationData::t('virtuemart_medias').' m ON m.virtuemart_media_id = pm.virtuemart_media_id WHERE pm.virtuemart_product_id = %d AND m.published = 1 ORDER BY pm.ordering', $old_id));
        }

        $slug = (string)($l['slug'] ?? '') !== '' ? $l['slug'] : sanitize_title($l['product_name'] ?? 'termek-'.$old_id);
        $path_url = '/termekek/'.implode('/', array_merge(array_filter(array_column($chain, 1)), [ $slug ]));
        $stock = (int)$p['product_in_stock'] - (int)($p['product_ordered'] ?? 0);
        return [
            'id' => (int)$old_id,
            'url' => MigrationSource::base().$path_url,
            'path_url' => $path_url,
            'all_paths' => [ $path_url ],
            'name' => trim(html_entity_decode(wp_strip_all_tags((string)($l['product_name'] ?? '')), ENT_QUOTES, 'UTF-8')),
            'sku' => trim((string)$p['product_sku']),
            'price' => $gross > 0 ? (string)$gross : '',
            'regular' => $regular > $gross ? (string)$regular : '',
            'availability' => $stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/BackOrder',
            'brand' => trim($brand),
            'path' => array_column($chain, 0),
            'description' => (string)($l['product_desc'] ?? '') !== '' ? $l['product_desc'] : (string)($l['product_s_desc'] ?? ''),
            'images' => array_values(array_filter(array_map('trim', $images))),
            'fields' => [],
            'documents' => [],
            'related' => [],
        ];
    }

    /** A régi kép helyben (ha a régi oldal fájljai a szerveren valahol még megvannak): teljes útvonal vagy ''. */
    static function local_file($file_url){
        $rel = ltrim(rawurldecode((string)parse_url($file_url, PHP_URL_PATH)), '/');
        if ($rel === '' || strpos($rel, '..') !== false) return '';
        $roots = [ ABSPATH, dirname(ABSPATH).'/' ];
        foreach((array)glob(dirname(ABSPATH).'/*', GLOB_ONLYDIR) as $dir) $roots[] = $dir.'/';
        foreach((array)glob(dirname(dirname(ABSPATH)).'/*', GLOB_ONLYDIR) as $dir) $roots[] = $dir.'/';
        foreach(array_unique($roots) as $root){
            if (is_file($root.$rel)) return $root.$rel;
        }
        return '';
    }

    /** Kép a médiatárba: helyi fájlból, különben a régi címről. Visszaad: csatolmány-azonosító vagy 0. */
    private static function image($file_url, $product_id, $title){
        $local = self::local_file($file_url);
        if ($local !== ''){
            require_once(ABSPATH.'wp-admin/includes/file.php');
            require_once(ABSPATH.'wp-admin/includes/media.php');
            require_once(ABSPATH.'wp-admin/includes/image.php');
            $tmp = wp_tempnam(basename($local));
            if (!@copy($local, $tmp)) return 0;
            $attachment = media_handle_sideload([ 'name' => sanitize_file_name(remove_accents(basename($local))), 'tmp_name' => $tmp ], (int)$product_id, $title);
            if (is_wp_error($attachment)){ @unlink($tmp); return 0; }
            update_post_meta($attachment, '_wp_attachment_image_alt', $title);
            return (int)$attachment;
        }
        $url = preg_match('~^https?://~', $file_url) ? $file_url : MigrationSource::base().'/'.ltrim($file_url, '/');
        return MigrationImporter::sideload($url, $product_id, $title);
    }

    /** Az új kategória: a megfeleltetés (régi útvonal → levél), különben a régi kategóriacím átirányítása. */
    private static function term(array $data){
        [ $leaf ] = MigrationClassifier::match($data);
        $leaves = MigrationClassifier::leaves();
        if ($leaf !== '' && !empty($leaves[$leaf][0])) return (int)$leaves[$leaf][0];
        $map = Migration::redirects()['categories'] ?? [];
        for ($try = Migration::normalize_path(dirname($data['path_url'])); $try !== '' && $try !== '.' && $try !== 'termekek'; $try = dirname($try)){
            if (!empty($map[$try])) return (int)$map[$try];
        }
        return 0;
    }

    /** A hiányzó termékek átvétele (egyszerre legfeljebb $limit). Visszaad: [ 'imported' => [...], 'skipped' => [...] ]. */
    static function run($limit = 25){
        @set_time_limit(300);
        $state = wp_parse_args((array)get_option(self::STATE, []), [ 'imported' => [], 'skipped' => [], 'runs' => 0 ]);
        $state['runs']++;
        foreach(self::missing($limit) as $item){
            if ($item['parent']){
                $state['skipped'][$item['id']] = $item['name'].' – méretváltozat, a szülője is hiányzik';
                continue;
            }
            $data = self::data($item['id']);
            if (!$data || $data['name'] === ''){
                $state['skipped'][$item['id']] = ($item['name'] ?: '#'.$item['id']).' – hiányos adat';
                continue;
            }
            $term = self::term($data);
            if (!$term){
                $state['skipped'][$item['id']] = $data['name'].' – nincs hozzá új kategória ('.implode(' > ', $data['path']).')';
                continue;
            }
            $images = $data['images'];
            $data['images'] = [];
            $record = Migration::upsert($data);
            if (!$record) continue;
            Migration::set_suggestion($record, [ 'term' => $term, 'source' => 'régi adatbázis', 'confidence' => 0.9, 'note' => 'A régi boltba később felvett termék (adatbázisból)' ], 'ready');
            $product_id = MigrationImporter::import_one($record);
            if (is_wp_error($product_id)){
                $state['skipped'][$item['id']] = $data['name'].' – '.$product_id->get_error_message();
                continue;
            }
            $attachments = [];
            foreach($images as $file) if (($a = self::image($file, $product_id, $data['name']))) $attachments[] = $a;
            $found = count($attachments);
            if ($attachments){
                $product = wc_get_product($product_id);
                $product->set_image_id(array_shift($attachments));
                $product->set_gallery_image_ids($attachments);
                $product->save();
            }
            Migration::add_redirect('products', $data['path_url'], $product_id);
            $state['imported'][$item['id']] = [ 'product' => (int)$product_id, 'name' => $data['name'], 'sku' => $data['sku'], 'price' => $data['price'], 'images' => count($images), 'images_found' => $found, 'url' => get_permalink($product_id) ];
        }
        $state['last'] = time();
        update_option(self::STATE, $state, false);
        wc_delete_product_transients();
        delete_transient('wc_term_counts');
        MegaMenu::flush();
        Feed::flush();
        return $state;
    }
}
