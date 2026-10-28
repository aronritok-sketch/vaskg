<?php

namespace VasskisgepTheme;

/**
 * Méretek (és más választható változatok) a régi webshopból (ügyfélkérés, 2026-10): a régi boltban a ruhák
 * minden mérete külön termék volt („FUNCTION ERGO kabát M”, „… L”), a gyűjtőtermék oldalán egy „Méret”
 * legördülő vitt át közöttük. Az átköltöztetés csak a gyűjtőterméket hozta át, méretek nélkül.
 *
 * Az importált termékeken (_vk_old_url) adagonként végigmegy: ha a régi oldalon van ilyen választó, a méretek
 * oldalait is letölti (cikkszám, ár, akció, készlet), és a terméket változatos termékké alakítja (helyi
 * „Méret” tulajdonság, méretenként egy változat). A méretek régi címei a termékre irányítanak. A háttérben
 * fut (Action Scheduler), az átköltöztetés oldalán kézzel is indítható; a régi boltnak elérhetőnek kell lennie.
 */
class MigrationVariants {
    const STATE = 'vk_mig_variants';
    const LOCK = 'vk_mig_variants_lock';
    const HOOK = 'vasskisgep_variants_step';
    const PER_STEP = 12;
    const SIZE_ORDER = [ 'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', 'XXXL', '3XL', '4XL', '5XL' ];

    static function init(){
        add_action(self::HOOK, [__CLASS__, 'background']);
    }

    static function state(){
        return wp_parse_args((array)get_option(self::STATE, []), [ 'offset' => 0, 'total' => 0, 'products' => 0, 'variations' => 0, 'skipped' => 0, 'errors' => 0, 'started' => 0, 'finished' => 0 ]);
    }

    static function reset(){
        update_option(self::STATE, [ 'offset' => 0, 'total' => 0, 'products' => 0, 'variations' => 0, 'skipped' => 0, 'errors' => 0, 'started' => time(), 'finished' => 0 ], false);
    }

    static function start(){
        self::reset();
        self::schedule();
    }

    static function schedule(){
        if (function_exists('as_enqueue_async_action')) as_enqueue_async_action(self::HOOK, [], 'vasskisgep');
        else wp_schedule_single_event(time(), self::HOOK);
    }

    static function background(){
        $result = self::step();
        if (empty($result['done']) && empty($result['locked'])) self::schedule();
    }

    private static function product_ids(){
        global $wpdb;
        return array_map('intval', $wpdb->get_col("SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_vk_old_url' AND pm.meta_value <> '' AND p.post_type = 'product' AND p.post_status NOT IN ('trash', 'auto-draft') ORDER BY pm.post_id"));
    }

    /** Egy adag termék. Visszaad: [ done, message ]. */
    static function step($limit = self::PER_STEP){
        if (get_transient(self::LOCK)) return [ 'done' => false, 'locked' => true, 'message' => 'Egy adag éppen fut a háttérben…' ];
        set_transient(self::LOCK, 1, 5 * MINUTE_IN_SECONDS);
        @set_time_limit(240);

        $state = self::state();
        if (empty($state['started'])){
            self::reset();
            $state = self::state();
        }
        $ids = self::product_ids();
        $state['total'] = count($ids);

        $paths = [];
        foreach(array_slice($ids, (int)$state['offset'], $limit) as $id){
            $state['offset']++;
            if (get_post_meta($id, '_vk_variants', true)) continue;
            $path = (string)parse_url((string)get_post_meta($id, '_vk_old_url', true), PHP_URL_PATH);
            if ($path !== '') $paths[$id] = $path;
        }
        foreach(array_chunk($paths, MigrationSource::PARALLEL, true) as $chunk){
            $pages = MigrationSource::fetch_many(array_values(array_unique($chunk)));
            foreach($chunk as $id => $path){
                $html = $pages[$path] ?? null;
                if (!is_string($html)){ $state['errors']++; continue; }
                $result = self::convert($id, $path, $html);
                if ($result === null) continue;
                if (is_wp_error($result)){ $state['skipped']++; continue; }
                $state['products']++;
                $state['variations'] += $result;
            }
        }

        $done = $state['offset'] >= $state['total'];
        if ($done) $state['finished'] = time();
        update_option(self::STATE, $state, false);
        delete_transient(self::LOCK);
        return [ 'done' => $done, 'message' => self::message($state) ];
    }

    static function message($state){
        return sprintf('%d / %d termék átnézve, %d termék lett méretválasztós (%d változat)%s%s.', min($state['offset'], $state['total']), $state['total'], $state['products'], $state['variations'],
            $state['skipped'] ? ', '.$state['skipped'].' kihagyva (a méret külön termékként is megvan)' : '',
            $state['errors'] ? ', '.$state['errors'].' régi oldal nem töltődött le' : '');
    }

    /**
     * A régi oldal változat-választója: [ 'label' => 'Méret', 'items' => [ [ 'path' => …, 'text' => … ], … ] ], vagy null.
     * A „Válasszon …!” első sor (a gyűjtőtermék címe) nem változat.
     */
    static function options($html){
        if (!preg_match('~<div class="product-field product-field-type-A[^"]*">.*?<span class="product-fields-title">(.*?)</span>.*?<select[^>]*>(.*?)</select>~s', (string)$html, $m)) return null;
        $label = trim(html_entity_decode(wp_strip_all_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        preg_match_all('~<option value="([^"]*)"[^>]*>(.*?)</option>~s', $m[2], $options, PREG_SET_ORDER);
        $items = [];
        foreach($options as $option){
            $path = html_entity_decode($option[1], ENT_QUOTES, 'UTF-8');
            $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($option[2]), ENT_QUOTES, 'UTF-8')));
            if (strpos($path, '/termekek/') !== 0 || $text === '' || preg_match('/^válassz/iu', $text)) continue;
            $items[] = [ 'path' => $path, 'text' => $text ];
        }
        return count($items) > 1 ? [ 'label' => $label !== '' ? $label : 'Méret', 'items' => $items ] : null;
    }

    /** A változatok neve a közös (termék)név nélkül: „FUNCTION ERGO kabát M” → „M”. */
    static function values(array $texts){
        $words = array_map(function($t){ return preg_split('/\s+/u', $t); }, $texts);
        $common = 0;
        while (true){
            $word = $words[0][$common] ?? null;
            if ($word === null) break;
            foreach($words as $w){ if (($w[$common] ?? null) !== $word || count($w) <= $common + 1){ break 2; } }
            $common++;
        }
        return array_map(function($w) use ($common){ return implode(' ', array_slice($w, $common)); }, $words);
    }

    /**
     * A termék átalakítása változatossá a régi oldal választója alapján. Visszaad: a változatok száma, null ha
     * nincs választó, WP_Error ha nem alakítható (a termék maga is egy méret, vagy a méretek külön termékként megvannak).
     */
    static function convert($product_id, $path, $html){
        $options = self::options($html);
        if (!$options) return null;
        $redirects = Migration::redirects()['products'];
        foreach($options['items'] as $item){
            $target = (int)($redirects[Migration::normalize_path($item['path'])] ?? 0);
            if (Migration::normalize_path($item['path']) === Migration::normalize_path($path) || ($target && $target !== (int)$product_id && get_post_status($target) === 'publish')){
                return new \WP_Error('separate', 'a méretek külön termékként is megvannak');
            }
        }

        $paths = array_values(array_unique(wp_list_pluck($options['items'], 'path')));
        $pages = MigrationSource::fetch_many($paths);
        $values = self::values(wp_list_pluck($options['items'], 'text'));
        $rows = [];
        foreach($options['items'] as $i => $item){
            $page = $pages[$item['path']] ?? null;
            $data = is_string($page) ? MigrationSource::parse($item['path'], $page) : null;
            if (!$data) continue;
            $rows[] = [ 'value' => $values[$i] !== '' ? $values[$i] : $item['text'], 'path' => $item['path'], 'data' => $data ];
        }
        if (count($rows) < 2) return new \WP_Error('fetch', 'a méretek oldalai nem töltődtek le');

        // Méretsorrend: XS, S, M, L, XL …; számos méretek (pl. 42, 44) növekvőben; egyébként a régi sorrend.
        usort($rows, function($a, $b){
            $order = array_flip(self::SIZE_ORDER);
            $x = strtoupper($a['value']); $y = strtoupper($b['value']);
            if (isset($order[$x], $order[$y])) return $order[$x] <=> $order[$y];
            if (is_numeric(str_replace(',', '.', $x)) && is_numeric(str_replace(',', '.', $y))) return (float)str_replace(',', '.', $x) <=> (float)str_replace(',', '.', $y);
            return 0;
        });

        $product = wc_get_product($product_id);
        if (!$product) return new \WP_Error('product', 'a termék nem található');
        $key = sanitize_title($options['label']);
        $attributes = [];
        foreach($product->get_attributes() as $name => $attribute){
            if ($name !== $key) $attributes[$name] = clone $attribute;
        }
        $attribute = new \WC_Product_Attribute();
        $attribute->set_name($options['label']);
        $attribute->set_options(wp_list_pluck($rows, 'value'));
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        $attribute->set_position(0);
        $attributes = array_merge([ $key => $attribute ], $attributes);

        wp_set_object_terms($product_id, 'variable', 'product_type');
        clean_post_cache($product_id);
        $variable = new \WC_Product_Variable($product_id);
        $variable->set_attributes($attributes);
        $variable->set_regular_price('');
        $variable->set_sale_price('');
        $variable->set_default_attributes([]);
        $variable->save();

        foreach($variable->get_children() as $child){
            $old = wc_get_product($child);
            if ($old) $old->delete(true);
        }
        foreach($rows as $row){
            $data = $row['data'];
            $variation = new \WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $variation->set_attributes([ $key => $row['value'] ]);
            $price = (float)($data['price'] ?? 0);
            $regular = (float)($data['regular'] ?? 0);
            if ($regular > $price && $price > 0){
                $variation->set_regular_price((string)round($regular));
                $variation->set_sale_price((string)round($price));
            } else {
                $variation->set_regular_price($price > 0 ? (string)round($price) : '');
            }
            $variation->set_manage_stock(false);
            $variation->set_stock_status(substr((string)($data['availability'] ?? ''), -7) === 'InStock' ? 'instock' : 'onbackorder');
            $sku = trim((string)($data['sku'] ?? ''));
            if ($sku !== '' && !wc_get_product_id_by_sku($sku)) $variation->set_sku($sku);
            $variation->update_meta_data('_vk_old_url', esc_url_raw(MigrationSource::base().$row['path']));
            $variation->save();
            Migration::add_redirect('products', $row['path'], $product_id);
        }
        \WC_Product_Variable::sync($product_id);
        wc_delete_product_transients($product_id);
        update_post_meta($product_id, '_vk_variants', count($rows));
        return count($rows);
    }
}
