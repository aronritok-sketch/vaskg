<?php

namespace VasskisgepTheme;

/**
 * Kapcsolódó termékek a régi webshopból (ügyfélkérés, 2026-10): a régi termékoldalak „Kapcsolódó
 * termékek” sávja (olajok, kerekek, tartozékok) az új termék keresztértékesítése lesz, ami a
 * termékoldal jobb oldali „Kapcsolódó termékek” listájában jelenik meg, kosárba gombbal.
 *
 * Az importált termékeken (_vk_old_url) adagonként végigmegy, a régi oldalt újra letölti; a
 * kapcsolódó terméket a régi címe (átirányítási tábla) vagy a cikkszáma alapján találja meg. Csak
 * ott állít be, ahol még nincs keresztértékesítés (a kézzel beállított marad). A háttérben fut
 * (Action Scheduler), az átköltöztetés oldalán kézzel is indítható. A régi boltnak elérhetőnek kell
 * lennie: a domain átállítása előtt kell lefutnia.
 */
class MigrationRelated {
    const STATE = 'vk_mig_related';
    const LOCK = 'vk_mig_related_lock';
    const HOOK = 'vasskisgep_related_step';
    const PER_STEP = 24;
    const LIMIT = 12;

    static function init(){
        add_action(self::HOOK, [__CLASS__, 'background']);
    }

    static function state(){
        return wp_parse_args((array)get_option(self::STATE, []), [ 'offset' => 0, 'total' => 0, 'products' => 0, 'linked' => 0, 'errors' => 0, 'started' => 0, 'finished' => 0 ]);
    }

    static function reset(){
        update_option(self::STATE, [ 'offset' => 0, 'total' => 0, 'products' => 0, 'linked' => 0, 'errors' => 0, 'started' => time(), 'finished' => 0 ], false);
    }

    /** Indítás a háttérben. */
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
        @set_time_limit(180);

        $state = self::state();
        if (empty($state['started'])){
            self::reset();
            $state = self::state();
        }
        $ids = self::product_ids();
        $state['total'] = count($ids);

        $paths = [];
        foreach(array_slice($ids, (int)$state['offset'], $limit) as $id){
            $path = (string)parse_url((string)get_post_meta($id, '_vk_old_url', true), PHP_URL_PATH);
            if ($path !== '') $paths[$id] = $path;
            $state['offset']++;
        }
        foreach(array_chunk($paths, MigrationSource::PARALLEL, true) as $chunk){
            $pages = MigrationSource::fetch_many(array_values(array_unique($chunk)));
            foreach($chunk as $id => $path){
                $html = $pages[$path] ?? null;
                if (!is_string($html)){ $state['errors']++; continue; }
                $linked = self::apply($id, MigrationSource::related($html));
                if ($linked){ $state['products']++; $state['linked'] += $linked; }
            }
        }

        $done = $state['offset'] >= $state['total'];
        if ($done) $state['finished'] = time();
        update_option(self::STATE, $state, false);
        delete_transient(self::LOCK);
        return [ 'done' => $done, 'message' => self::message($state) ];
    }

    static function message($state){
        return sprintf('%d / %d termék átnézve, %d termékhez %d kapcsolódó termék beállítva%s.', min($state['offset'], $state['total']), $state['total'], $state['products'], $state['linked'], $state['errors'] ? ', '.$state['errors'].' régi oldal nem töltődött le' : '');
    }

    /**
     * A régi kapcsolódó termékek beállítása keresztértékesítésnek, ha a terméknek még nincs.
     * $items: MigrationSource::related() kimenete. Visszaad: a beállított termékek száma.
     */
    static function apply($product_id, array $items){
        $product = wc_get_product($product_id);
        if (!$product || $product->get_cross_sell_ids() || empty($items)) return 0;
        $targets = [];
        foreach($items as $item){
            $target = self::find($item);
            if ($target && $target !== (int)$product_id && !in_array($target, $targets, true)) $targets[] = $target;
            if (count($targets) >= self::LIMIT) break;
        }
        if (empty($targets)) return 0;
        $product->set_cross_sell_ids($targets);
        $product->save();
        return count($targets);
    }

    /** Új termék a régi címe (az átirányítási táblából) vagy a cikkszáma alapján. */
    static function find(array $item){
        $products = Migration::redirects()['products'];
        $path = Migration::normalize_path((string)($item['path'] ?? ''));
        if ($path !== '' && !empty($products[$path]) && get_post_status($products[$path]) === 'publish') return (int)$products[$path];
        if (($item['sku'] ?? '') !== ''){
            $id = (int)wc_get_product_id_by_sku($item['sku']);
            if ($id && get_post_status($id) === 'publish') return $id;
        }
        return 0;
    }
}
