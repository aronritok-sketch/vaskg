<?php

namespace VasskisgepTheme;

/**
 * A régi webshop (Joomla 3 + VirtueMart 3) üzleti adatai az adatbázisából (az átálláskor, 2026-11): vásárlói fiókok
 * (számlázási / szállítási címmel, a régi jelszóval), rendelések (tételekkel, szállítással, fizetéssel, állapottal),
 * termékértékelések, kuponok – és az ÖSSZES régi tábla CSV-archívumban (feliratkozók, űrlapküldések, minden egyéb),
 * védett mappában, letölthetően.
 *
 * Forrás: a régi Joomla-adatbázis táblái ugyanebben az adatbázisban (phpMyAdmin: a régi adatbázis exportja → import
 * ide; a Joomla-előtag más, mint a WordPressé, nem ütköznek), vagy egy másik adatbázisban ugyanazon a szerveren,
 * ha a WordPress adatbázis-felhasználója olvashatja. Az előtagot magától megtalálja (…virtuemart_orders tábla).
 *
 * Újrafuttatható (az átállás napján friss mentéssel): a már átvett vásárló, rendelés, értékelés, kupon nem duplázódik
 * (régi azonosító a metaadatban), a régi rendelés állapota frissül. Az átvétel alatt nem megy ki levél, nem változik
 * a készlet, és nem fut le semmi, ami a rendelés állapotához kötött (számla, értesítés).
 */
class MigrationData {
    const OPTION = 'vk_mig_data';
    const STATE = 'vk_mig_data_state';
    const LOCK = 'vk_mig_data_lock';
    const STAGES = [ 'customers' => 'Vásárlók', 'orders' => 'Rendelések', 'reviews' => 'Értékelések', 'coupons' => 'Kuponok', 'archive' => 'Archívum (minden tábla)' ];
    const BATCH = [ 'customers' => 150, 'orders' => 40, 'reviews' => 200, 'coupons' => 200, 'archive' => 400 ];
    /** Az archívumból kihagyott oszlopok (jelszavak, titkok) és táblák (munkamenetek). */
    const SECRET_COLUMNS = [ 'password', 'otpkey', 'otep', 'activation', 'order_pass', 'order_create_invoice_pass', 'params_secret' ];
    const SKIP_TABLES = [ 'session' ];
    /** Régi rendelés nyitott állapotban, ennél régebben: lezártként kerül át (a napi munkát ne zavarja). */
    const OPEN_DAYS = 30;
    /** Magától induló átvétel (élesítés után, 2026-11): háttérlépés, és az állapotlap kulcsának sha256-ja (a kulcs nincs a repóban). */
    const HOOK = 'vasskisgep_data_step';
    const AUTO = 'vk_mig_data_auto';
    const STATUS_KEY_HASH = '0417b1a451bf0e0e8f8af7ebd309eb5e9fdebb89fdd6578a62e0d7228532bdae';

    private static $tables = null;
    private static $products = null;

    static function init(){
        add_action('admin_post_vk_mig_data_download', [__CLASS__, 'download']);
        add_action(self::HOOK, [__CLASS__, 'background']);
        add_action('vasskisgep_newproducts', function(){ MigrationNewProducts::run(); });
        add_action('init', [__CLASS__, 'auto'], 99);
        add_action('rest_api_init', function(){
            register_rest_route('vasskisgep/v1', '/data-status', [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'rest_status'],
                'permission_callback' => function($request){ return hash_equals(self::STATUS_KEY_HASH, hash('sha256', (string)$request->get_param('key'))); },
            ]);
        });
        // A régi rendelés a régi rendelésszámával látszik (admin, vásárlói fiók, levelek), és arra is kereshető.
        add_filter('woocommerce_order_number', function($number, $order){
            $old = $order instanceof \WC_Order ? (string)$order->get_meta('_vk_old_order_number') : '';
            return $old !== '' ? $old : $number;
        }, 10, 2);
        add_filter('woocommerce_shop_order_search_fields', function($fields){ $fields[] = '_vk_old_order_number'; return $fields; });
        add_filter('woocommerce_order_table_search_query_meta_keys', function($keys){ $keys[] = '_vk_old_order_number'; return $keys; });
    }

    /* ---------- Forrás ---------- */

    static function settings(){
        return wp_parse_args((array)get_option(self::OPTION, []), [ 'db' => '', 'prefix' => '' ]);
    }

    static function save_settings($db, $prefix){
        update_option(self::OPTION, [ 'db' => preg_replace('/[^A-Za-z0-9_$-]/', '', (string)$db), 'prefix' => preg_replace('/[^A-Za-z0-9_$]/', '', (string)$prefix) ], false);
        self::$tables = null;
    }

    private static function db_clause(){
        $db = self::settings()['db'];
        return $db !== '' ? ' FROM `'.$db.'`' : '';
    }

    /** A régi (VirtueMart) táblák előtagjai az adatbázisban. */
    static function detect(){
        global $wpdb;
        $found = [];
        foreach((array)$wpdb->get_col('SHOW TABLES'.self::db_clause()." LIKE '%virtuemart\\_orders'") as $table){
            $prefix = substr($table, 0, -strlen('virtuemart_orders'));
            if ($prefix !== $wpdb->prefix) $found[] = $prefix;
        }
        return $found;
    }

    /**
     * A régi táblák bármelyik elérhető adatbázisban (information_schema): [ [ 'db' => …, 'prefix' => … ], … ];
     * előbb a WordPress saját adatbázisa (oda importálva), utána a többi (a régi Joomla-adatbázis ugyanazon a szerveren).
     */
    static function locate(){
        global $wpdb;
        $rows = $wpdb->get_results("SELECT TABLE_SCHEMA AS db, TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_NAME LIKE '%virtuemart\\_orders' ORDER BY (TABLE_SCHEMA = DATABASE()) DESC, TABLE_SCHEMA", ARRAY_A);
        $out = [];
        foreach((array)$rows as $row){
            $prefix = substr($row['name'], 0, -strlen('virtuemart_orders'));
            $same = $row['db'] === DB_NAME;
            if ($same && $prefix === $wpdb->prefix) continue;
            $out[] = [ 'db' => $same ? '' : $row['db'], 'prefix' => $prefix ];
        }
        return $out;
    }

    /**
     * Magától indul (Áron, 2026-11-06: „hozd át magadtól a régiről”): ha a régi táblák elérhetők, és még nem futott le
     * ezzel a forrással, a háttérben lefut az egész átvétel (adagonként, Action Scheduler / WP-Cron). Óránként egyszer néz
     * körül, így ha a régi adatbázist később importálják, akkor indul.
     */
    static function auto(){
        if (!function_exists('WC') || wp_doing_ajax()) return;
        // Megakadt futás (pl. egy adag kifutott a memóriából / időből): 10 perc csend után folytatja.
        $state = self::state();
        if (!empty($state['started']) && empty($state['finished']) && get_option(self::AUTO) && (int)($state['touched'] ?? $state['started']) < time() - 10 * MINUTE_IN_SECONDS && !get_transient(self::AUTO.'_resume')){
            set_transient(self::AUTO.'_resume', 1, 10 * MINUTE_IN_SECONDS);
            delete_transient(self::LOCK);
            self::schedule();
        }
        // Kész átvétel, de a később felvett termékek még nem jöttek át: egyszer, a háttérben.
        if (!empty($state['finished']) && get_option(self::AUTO) && !get_option(MigrationNewProducts::STATE) && !get_transient(self::AUTO.'_newprod')){
            set_transient(self::AUTO.'_newprod', 1, HOUR_IN_SECONDS);
            if (function_exists('as_schedule_single_action')) as_schedule_single_action(time() + 5, 'vasskisgep_newproducts', [], 'vasskisgep');
            else wp_schedule_single_event(time() + 5, 'vasskisgep_newproducts');
        }
        if (get_transient(self::AUTO.'_probe')) return;
        set_transient(self::AUTO.'_probe', 1, HOUR_IN_SECONDS);
        $found = self::locate();
        if (!$found) return;
        $source = $found[0];
        $done = (array)get_option(self::AUTO, []);
        if (($done['db'] ?? null) === $source['db'] && ($done['prefix'] ?? null) === $source['prefix']) return;
        self::save_settings($source['db'], $source['prefix']);
        self::reset();
        update_option(self::AUTO, [ 'db' => $source['db'], 'prefix' => $source['prefix'], 'started' => time() ], false);
        self::schedule(5);
    }

    static function schedule($delay = 0){
        if (function_exists('as_schedule_single_action')) as_schedule_single_action(time() + $delay, self::HOOK, [], 'vasskisgep');
        else wp_schedule_single_event(time() + $delay, self::HOOK);
    }

    /** Egy háttéradag; ha nincs kész, a következő azonnal (hibánál 5 perc múlva, legfeljebb 20-szor). */
    static function background(){
        $result = self::step();
        if (is_wp_error($result)){
            $state = self::state();
            $state['notes'][] = wp_date('Y-m-d H:i').' '.$result->get_error_message();
            $state['errors'] = ($state['errors'] ?? 0) + 1;
            update_option(self::STATE, $state, false);
            if ($state['errors'] <= 20) self::schedule(5 * MINUTE_IN_SECONDS);
            return;
        }
        if (!empty($result['locked'])){ self::schedule(60); return; }
        if (empty($result['done'])) self::schedule();
        // A végén: a régi boltba később felvett (a beolvasásból kimaradt) termékek.
        elseif (class_exists(__NAMESPACE__.'\\MigrationNewProducts')) MigrationNewProducts::run();
    }

    /** Állapot (kulccsal): a forrás, a lépés, a számlálók – személyes adat nélkül. */
    static function rest_status(){
        global $wpdb;
        $state = self::state();
        $prefix = self::prefix();
        return [
            'located' => self::locate(),
            'settings' => self::settings(),
            'auto' => get_option(self::AUTO, []),
            'source' => $prefix !== '' ? self::source_counts() : [],
            'stage' => $state['stage'],
            'counts' => $state['counts'],
            'notes' => array_slice((array)$state['notes'], -10),
            'started' => $state['started'] ? wp_date('Y-m-d H:i', $state['started']) : '',
            'finished' => $state['finished'] ? wp_date('Y-m-d H:i', $state['finished']) : '',
            'message' => self::message($state),
            'wp_orders_migrated' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_vk_old_order_id'") + (int)($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wc_orders_meta'") ? $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = '_vk_old_order_id'") : 0),
            'wp_customers_migrated' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = '_vk_old_user_id'"),
            'latest_old_order' => $prefix !== '' && self::has('virtuemart_orders') ? (string)$wpdb->get_var('SELECT MAX(created_on) FROM '.self::t('virtuemart_orders')) : '',
            'missing_products' => $prefix !== '' ? MigrationNewProducts::missing(30) : [],
            'new_products' => get_option(MigrationNewProducts::STATE, []),
            'pending_actions' => function_exists('as_get_scheduled_actions') ? count(as_get_scheduled_actions([ 'hook' => self::HOOK, 'status' => 'pending' ], 'ids')) : null,
        ];
    }

    /** Az előtag (beállított, vagy ha egy van, a megtalált). */
    static function prefix(){
        $prefix = self::settings()['prefix'];
        if ($prefix !== '') return $prefix;
        $found = self::detect();
        return count($found) === 1 ? $found[0] : '';
    }

    /** A régi táblák (előtag nélküli névvel). */
    static function tables(){
        if (self::$tables !== null) return self::$tables;
        global $wpdb;
        $prefix = self::prefix();
        self::$tables = [];
        if ($prefix === '') return self::$tables;
        foreach((array)$wpdb->get_col('SHOW TABLES'.self::db_clause().' LIKE \''.esc_sql($wpdb->esc_like($prefix)).'%\'') as $table){
            self::$tables[] = substr($table, strlen($prefix));
        }
        sort(self::$tables);
        return self::$tables;
    }

    static function has($name){
        return in_array($name, self::tables(), true);
    }

    /** Teljes, idézett táblanév. */
    static function t($name){
        $db = self::settings()['db'];
        return ($db !== '' ? '`'.$db.'`.' : '').'`'.self::prefix().$name.'`';
    }

    static function columns($name){
        global $wpdb;
        return self::has($name) ? (array)$wpdb->get_col('SHOW COLUMNS FROM '.self::t($name)) : [];
    }

    /** Nyelvi tábla (pl. virtuemart_paymentmethods_hu_hu): a magyar, vagy ha nincs, az első. */
    static function lang_table($base){
        $all = array_values(array_filter(self::tables(), function($t) use ($base){ return strpos($t, $base.'_') === 0 && preg_match('/_[a-z]{2}_[a-z]{2}$/', $t); }));
        if (in_array($base.'_hu_hu', $all, true)) return $base.'_hu_hu';
        return $all[0] ?? '';
    }

    /** Számlálók a felülethez. */
    static function source_counts(){
        global $wpdb;
        $out = [];
        foreach([ 'users' => 'felhasználó', 'virtuemart_orders' => 'rendelés', 'virtuemart_rating_reviews' => 'értékelés', 'virtuemart_coupons' => 'kupon' ] as $table => $label){
            if (self::has($table)) $out[$label] = (int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::t($table));
        }
        $out['tábla'] = count(self::tables());
        return $out;
    }

    /* ---------- Állapot ---------- */

    static function state(){
        return wp_parse_args((array)get_option(self::STATE, []), [ 'stage' => 'customers', 'last' => 0, 'table' => 0, 'offset' => 0, 'counts' => [], 'notes' => [], 'started' => 0, 'finished' => 0 ]);
    }

    static function reset(){
        delete_transient(self::LOCK);
        update_option(self::STATE, [ 'stage' => 'customers', 'last' => 0, 'table' => 0, 'offset' => 0, 'counts' => [], 'notes' => [], 'started' => time(), 'finished' => 0 ], false);
    }

    private static function count(&$state, $key, $n = 1){
        $state['counts'][$key] = ($state['counts'][$key] ?? 0) + $n;
    }

    static function message($state){
        $c = $state['counts'];
        $parts = [];
        $labels = [
            'customers_new' => 'új vásárló', 'customers_linked' => 'meglévő fiókhoz kötve', 'customers_skipped' => 'fiók kihagyva (adminisztrátor, letiltott vagy hibás e-mail)',
            'orders_new' => 'új rendelés', 'orders_updated' => 'rendelés állapota frissítve', 'items_unmatched' => 'rendelési tétel termék nélkül (a régi néven)',
            'reviews_new' => 'értékelés', 'reviews_unmatched' => 'értékelés kihagyva (nincs meg a termék)', 'coupons_new' => 'kupon',
            'subscribers' => 'feliratkozó', 'archive_rows' => 'sor archiválva', 'archive_tables' => 'tábla',
        ];
        foreach($labels as $key => $label) if (!empty($c[$key])) $parts[] = number_format_i18n($c[$key]).' '.$label;
        $stage = self::STAGES[$state['stage']] ?? '';
        $head = $state['finished'] ? 'Kész' : ($stage !== '' ? 'Folyamatban: '.$stage : 'Folyamatban');
        return $head.($parts ? ' – '.implode(', ', $parts) : '').'.'.($state['notes'] ? "\n".implode("\n", array_slice($state['notes'], -5)) : '');
    }

    /* ---------- Futtatás ---------- */

    /** Egy adag. Visszaad: [ done, message ] vagy WP_Error. */
    static function step(){
        if (self::prefix() === ''){
            $found = self::detect();
            return new \WP_Error('source', $found ? 'Több régi adatbázis-előtag van ('.implode(', ', $found).'): add meg, melyiket használja.' : 'Nem találom a régi webshop tábláit (…virtuemart_orders). A régi Joomla-adatbázist importáld ebbe az adatbázisba (phpMyAdmin), vagy add meg az adatbázis nevét.');
        }
        if (get_transient(self::LOCK)) return [ 'done' => false, 'locked' => true, 'message' => 'Egy adag éppen fut…' ];
        set_transient(self::LOCK, 1, 5 * MINUTE_IN_SECONDS);
        @set_time_limit(300);
        wp_raise_memory_limit('admin');
        self::quiet();

        $state = self::state();
        if (empty($state['started'])){
            self::reset();
            $state = self::state();
        }
        try {
            $method = 'stage_'.(isset(self::STAGES[$state['stage']]) ? $state['stage'] : 'archive');
            $more = self::$method($state);
        } catch (\Throwable $e){
            delete_transient(self::LOCK);
            return new \WP_Error('fail', 'Hiba ('.(self::STAGES[$state['stage']] ?? $state['stage']).'): '.$e->getMessage());
        }
        if (!$more){
            $keys = array_keys(self::STAGES);
            $next = array_search($state['stage'], $keys, true) + 1;
            if ($next < count($keys)){
                $state['stage'] = $keys[$next];
                $state['last'] = 0;
                $state['table'] = 0;
                $state['offset'] = 0;
            } else {
                $state['finished'] = time();
            }
        }
        $state['touched'] = time();
        update_option(self::STATE, $state, false);
        delete_transient(self::LOCK);
        return [ 'done' => !empty($state['finished']), 'message' => self::message($state) ];
    }

    /** Az átvétel alatt: levél nem megy ki, és a rendelés-állapothoz kötött műveletek (készlet, számla, értesítés) nem futnak. */
    static function quiet(){
        add_filter('pre_wp_mail', '__return_false', 999);
        add_filter('woocommerce_mail_callback', function(){ return '__return_false'; }, 999);
        $statuses = array_map(function($s){ return substr($s, 0, 3) === 'wc-' ? substr($s, 3) : $s; }, array_keys(wc_get_order_statuses()));
        $hooks = [ 'woocommerce_new_order', 'woocommerce_update_order', 'woocommerce_order_status_changed', 'woocommerce_payment_complete', 'woocommerce_order_partially_refunded', 'woocommerce_order_fully_refunded', 'woocommerce_order_refunded' ];
        foreach($statuses as $from){
            $hooks[] = 'woocommerce_order_status_'.$from;
            $hooks[] = 'woocommerce_payment_complete_order_status_'.$from;
            foreach($statuses as $to) $hooks[] = 'woocommerce_order_status_'.$from.'_to_'.$to;
        }
        foreach($hooks as $hook) remove_all_actions($hook);
    }

    /* ---------- Segédek ---------- */

    private static function rows($sql){
        global $wpdb;
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if ($wpdb->last_error) throw new \RuntimeException($wpdb->last_error);
        return (array)$rows;
    }

    private static function clean($value){
        return trim(html_entity_decode(wp_strip_all_tags((string)$value), ENT_QUOTES, 'UTF-8'));
    }

    /** Joomla dátum (UTC) → időbélyeg; üres / nullás dátumnál 0. */
    private static function time($value){
        $value = (string)$value;
        if ($value === '' || strpos($value, '0000-00-00') === 0) return 0;
        $time = strtotime($value.' UTC');
        return $time ?: 0;
    }

    private static function countries(){
        static $map = null;
        if ($map !== null) return $map;
        $map = [];
        if (self::has('virtuemart_countries')){
            foreach(self::rows('SELECT virtuemart_country_id, country_2_code FROM '.self::t('virtuemart_countries')) as $row) $map[(int)$row['virtuemart_country_id']] = strtoupper((string)$row['country_2_code']);
        }
        return $map;
    }

    /** Régi felhasználó → WordPress-felhasználó. */
    private static function user_map(){
        static $map = null;
        if ($map !== null) return $map;
        global $wpdb;
        $map = [];
        foreach($wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = '_vk_old_user_id'") as $row) $map[(int)$row->meta_value] = (int)$row->user_id;
        return $map;
    }

    /** Régi termékazonosító → WooCommerce termék (a termékimport _vk_old_id-je). */
    private static function product_map(){
        if (self::$products !== null) return self::$products;
        global $wpdb;
        self::$products = [];
        foreach($wpdb->get_results("SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_vk_old_id' AND p.post_type = 'product'") as $row) self::$products[(int)$row->meta_value] = (int)$row->post_id;
        return self::$products;
    }

    /** A cím a régi felhasználói / rendelési adatsorból (BT: számlázási, ST: szállítási). */
    private static function address(array $row){
        $first = self::clean($row['first_name'] ?? '');
        $last = self::clean($row['last_name'] ?? '');
        if ($first === '' && $last === '' && !empty($row['name'])){
            // Magyar névsorrend: „Vezetéknév Keresztnév”.
            $parts = preg_split('/\s+/u', self::clean($row['name']), 2);
            $last = $parts[0];
            $first = $parts[1] ?? '';
        }
        $country = self::countries()[(int)($row['virtuemart_country_id'] ?? 0)] ?? '';
        $address = [
            'first_name' => $first,
            'last_name' => $last,
            'company' => self::clean($row['company'] ?? ''),
            'address_1' => self::clean($row['address_1'] ?? ''),
            'address_2' => self::clean($row['address_2'] ?? ''),
            'city' => self::clean($row['city'] ?? ''),
            'postcode' => self::clean($row['zip'] ?? ''),
            'country' => $country !== '' ? $country : 'HU',
            'phone' => self::clean(($row['phone_1'] ?? '') !== '' ? $row['phone_1'] : ($row['phone_2'] ?? '')),
        ];
        if (!empty($row['email'])) $address['email'] = sanitize_email($row['email']);
        return $address;
    }

    /** Adószám a régi (egyedi) felhasználói mezőkből. */
    private static function tax_number(array $row){
        foreach($row as $key => $value){
            if (preg_match('/ad[oó]_?sz|tax_?(number|id|no)|vat/i', $key) && trim((string)$value) !== '' && !preg_match('/virtuemart|_id$/', $key)) return self::clean($value);
        }
        return '';
    }

    /** A régi címadatok felhasználónként (BT / ST). */
    private static function userinfos(array $user_ids){
        $out = [];
        if (!$user_ids || !self::has('virtuemart_userinfos')) return $out;
        foreach(self::rows('SELECT * FROM '.self::t('virtuemart_userinfos').' WHERE virtuemart_user_id IN ('.implode(',', array_map('intval', $user_ids)).') ORDER BY virtuemart_userinfo_id') as $row){
            $type = strtoupper((string)$row['address_type']) === 'ST' ? 'ST' : 'BT';
            if (!isset($out[(int)$row['virtuemart_user_id']][$type])) $out[(int)$row['virtuemart_user_id']][$type] = $row;
        }
        return $out;
    }

    /* ---------- 1. Vásárlók ---------- */

    private static function stage_customers(&$state){
        if (!self::has('users')) return false;
        $limit = self::BATCH['customers'];
        $users = self::rows('SELECT * FROM '.self::t('users').' WHERE id > '.(int)$state['last'].' ORDER BY id LIMIT '.$limit);
        if (!$users) return false;

        $ids = array_map(function($u){ return (int)$u['id']; }, $users);
        $admins = [];
        if (self::has('user_usergroup_map')){
            // Joomla: 7 Administrator, 8 Super Users (a régi oldal kezelői, nem vásárlók).
            $admins = array_map('intval', array_column(self::rows('SELECT user_id FROM '.self::t('user_usergroup_map').' WHERE group_id IN (7, 8) AND user_id IN ('.implode(',', $ids).')'), 'user_id'));
        }
        $with_orders = [];
        if (self::has('virtuemart_orders')){
            $with_orders = array_map('intval', array_column(self::rows('SELECT DISTINCT virtuemart_user_id FROM '.self::t('virtuemart_orders').' WHERE virtuemart_user_id IN ('.implode(',', $ids).')'), 'virtuemart_user_id'));
        }
        $infos = self::userinfos($ids);
        $map = self::user_map();

        foreach($users as $user){
            $old = (int)$user['id'];
            $state['last'] = $old;
            $email = sanitize_email(strtolower(trim((string)$user['email'])));
            if (in_array($old, $admins, true) || !is_email($email) || ((int)$user['block'] && !in_array($old, $with_orders, true))){
                self::count($state, 'customers_skipped');
                continue;
            }
            $bt = self::address(($infos[$old]['BT'] ?? []) + [ 'name' => $user['name'], 'email' => $email ]);
            $st = isset($infos[$old]['ST']) ? self::address($infos[$old]['ST']) : null;

            $user_id = $map[$old] ?? 0;
            if (!$user_id){
                $existing = get_user_by('email', $email);
                if ($existing){
                    // Már van fiók ezzel az e-maillel (pl. a próbarendelésekből): ahhoz kötjük, a jelszó és a szerep marad.
                    $user_id = $existing->ID;
                    self::count($state, 'customers_linked');
                } else {
                    $login = sanitize_user((string)$user['username'], true);
                    if ($login === '' || username_exists($login)) $login = $email;
                    $user_id = wp_insert_user([
                        'user_login' => $login,
                        'user_email' => $email,
                        'user_pass' => wp_generate_password(32),
                        'display_name' => trim($bt['last_name'].' '.$bt['first_name']) ?: $login,
                        'first_name' => $bt['first_name'],
                        'last_name' => $bt['last_name'],
                        'role' => 'customer',
                        'user_registered' => self::time($user['registerDate']) ? gmdate('Y-m-d H:i:s', self::time($user['registerDate'])) : current_time('mysql', true),
                    ]);
                    if (is_wp_error($user_id)){
                        $state['notes'][] = 'Vásárló kihagyva ('.$email.'): '.$user_id->get_error_message();
                        self::count($state, 'customers_skipped');
                        continue;
                    }
                    // A régi jelszó (Joomla 3: bcrypt) a WordPressben is működik; régebbi formátumnál új jelszót kell kérnie.
                    $hash = (string)$user['password'];
                    if (preg_match('/^\$(2[aby]|argon2i|argon2id)\$/', $hash)){
                        global $wpdb;
                        $wpdb->update($wpdb->users, [ 'user_pass' => $hash ], [ 'ID' => $user_id ]);
                        clean_user_cache($user_id);
                    }
                    self::count($state, 'customers_new');
                }
                update_user_meta($user_id, '_vk_old_user_id', $old);
                update_user_meta($user_id, '_vk_old_username', (string)$user['username']);
            }

            $customer = new \WC_Customer($user_id);
            foreach($bt as $key => $value){
                $getter = 'get_billing_'.$key;
                $setter = 'set_billing_'.$key;
                if ($value !== '' && method_exists($customer, $setter) && (string)$customer->$getter() === '') $customer->$setter($value);
            }
            if ($st){
                foreach($st as $key => $value){
                    $getter = 'get_shipping_'.$key;
                    $setter = 'set_shipping_'.$key;
                    if ($key !== 'email' && $value !== '' && method_exists($customer, $setter) && (string)$customer->$getter() === '') $customer->$setter($value);
                }
            }
            $tax = self::tax_number($infos[$old]['BT'] ?? []);
            if ($tax !== '' && !get_user_meta($user_id, 'billing_tax_number', true)) $customer->update_meta_data('billing_tax_number', $tax);
            $customer->save();
        }
        return count($users) === $limit;
    }

    /* ---------- 2. Rendelések ---------- */

    /** VirtueMart állapotkód → WooCommerce állapot. A régóta nyitott régi rendelés lezártként (fizetésre váró: törölt). */
    static function status($code, $created){
        $code = strtoupper((string)$code);
        $old = $created && $created < time() - self::OPEN_DAYS * DAY_IN_SECONDS;
        switch ($code){
            case 'P': return $old ? 'cancelled' : 'pending';
            case 'U': return $old ? 'completed' : 'on-hold';
            case 'C': return $old ? 'completed' : 'processing';
            case 'S': case 'F': return 'completed';
            case 'X': return 'cancelled';
            case 'R': return 'refunded';
            case 'D': return 'failed';
        }
        return $old ? 'completed' : 'on-hold';
    }

    private static function status_names(){
        static $names = null;
        if ($names !== null) return $names;
        $names = [ 'P' => 'Függőben', 'U' => 'Vásárló által megerősítve', 'C' => 'Megerősítve', 'S' => 'Kiszállítva', 'F' => 'Teljesítve', 'X' => 'Törölve', 'R' => 'Visszatérítve', 'D' => 'Elutasítva' ];
        if (self::has('virtuemart_orderstates')){
            foreach(self::rows('SELECT order_status_code, order_status_name FROM '.self::t('virtuemart_orderstates')) as $row){
                $name = (string)$row['order_status_name'];
                if (!isset($names[$row['order_status_code']]) && strpos($name, 'COM_VIRTUEMART') !== 0) $names[$row['order_status_code']] = $name;
            }
        }
        return $names;
    }

    private static function method_names($base, $key, $name){
        static $cache = [];
        if (isset($cache[$base])) return $cache[$base];
        $cache[$base] = [];
        $table = self::lang_table($base);
        if ($table !== ''){
            foreach(self::rows('SELECT '.$key.' AS id, '.$name.' AS name FROM '.self::t($table)) as $row) $cache[$base][(int)$row['id']] = self::clean($row['name']);
        }
        return $cache[$base];
    }

    /** Az áfakulcs (27%) azonosítója a WooCommerce adótáblában (0, ha nincs). */
    private static function tax_rate(){
        static $rate = null;
        if ($rate !== null) return $rate;
        $rate = [ 'id' => 0, 'percent' => 27.0 ];
        if (!wc_tax_enabled()) return $rate;
        foreach(\WC_Tax::find_rates([ 'country' => 'HU' ]) as $id => $row){
            $rate = [ 'id' => (int)$id, 'percent' => (float)$row['rate'] ];
            break;
        }
        return $rate;
    }

    /** Bruttó összegből [ nettó, áfa ]. */
    private static function split($gross){
        $rate = self::tax_rate();
        if (!$rate['id']) return [ round((float)$gross, 2), 0.0 ];
        $net = round((float)$gross / (1 + $rate['percent'] / 100), 2);
        return [ $net, round((float)$gross - $net, 2) ];
    }

    private static function taxes($tax){
        $rate = self::tax_rate();
        return $rate['id'] ? [ 'subtotal' => [ $rate['id'] => $tax ], 'total' => [ $rate['id'] => $tax ] ] : [ 'subtotal' => [], 'total' => [] ];
    }

    /** A már átvett rendelés (régi azonosító alapján; HPOS és régi tárolás). */
    private static function find_order($old_id){
        global $wpdb;
        if (class_exists('\\Automattic\\WooCommerce\\Utilities\\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()){
            return (int)$wpdb->get_var($wpdb->prepare("SELECT m.order_id FROM {$wpdb->prefix}wc_orders_meta m JOIN {$wpdb->prefix}wc_orders o ON o.id = m.order_id WHERE m.meta_key = '_vk_old_order_id' AND m.meta_value = %s AND o.type = 'shop_order' AND o.status <> 'trash' LIMIT 1", (string)(int)$old_id));
        }
        return (int)$wpdb->get_var($wpdb->prepare("SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_vk_old_order_id' AND pm.meta_value = %s AND p.post_type = 'shop_order' AND p.post_status <> 'trash' LIMIT 1", (string)(int)$old_id));
    }

    private static function stage_orders(&$state){
        if (!self::has('virtuemart_orders')) return false;
        $limit = self::BATCH['orders'];
        $orders = self::rows('SELECT * FROM '.self::t('virtuemart_orders').' WHERE virtuemart_order_id > '.(int)$state['last'].' ORDER BY virtuemart_order_id LIMIT '.$limit);
        if (!$orders) return false;
        $ids = array_map(function($o){ return (int)$o['virtuemart_order_id']; }, $orders);
        $in = implode(',', $ids);

        $items = [];
        if (self::has('virtuemart_order_items')){
            foreach(self::rows('SELECT * FROM '.self::t('virtuemart_order_items').' WHERE virtuemart_order_id IN ('.$in.') ORDER BY virtuemart_order_item_id') as $row) $items[(int)$row['virtuemart_order_id']][] = $row;
        }
        $infos = [];
        if (self::has('virtuemart_order_userinfos')){
            foreach(self::rows('SELECT * FROM '.self::t('virtuemart_order_userinfos').' WHERE virtuemart_order_id IN ('.$in.') ORDER BY virtuemart_order_userinfo_id') as $row){
                $type = strtoupper((string)$row['address_type']) === 'ST' ? 'ST' : 'BT';
                if (!isset($infos[(int)$row['virtuemart_order_id']][$type])) $infos[(int)$row['virtuemart_order_id']][$type] = $row;
            }
        }
        $history = [];
        if (self::has('virtuemart_order_histories')){
            foreach(self::rows('SELECT * FROM '.self::t('virtuemart_order_histories').' WHERE virtuemart_order_id IN ('.$in.') ORDER BY virtuemart_order_history_id') as $row) $history[(int)$row['virtuemart_order_id']][] = $row;
        }
        $payments = self::method_names('virtuemart_paymentmethods', 'virtuemart_paymentmethod_id', 'payment_name');
        $shipments = self::method_names('virtuemart_shipmentmethods', 'virtuemart_shipmentmethod_id', 'shipment_name');
        $users = self::user_map();
        $products = self::product_map();
        $names = self::status_names();

        foreach($orders as $row){
            $old = (int)$row['virtuemart_order_id'];
            $state['last'] = $old;
            $created = self::time($row['created_on'] ?? '');
            $status = self::status($row['order_status'] ?? '', $created);

            $existing = self::find_order($old);
            if ($existing){
                $order = wc_get_order($existing);
                if ($order && $order->get_meta('_vk_old_status') !== (string)$row['order_status']){
                    $order->update_meta_data('_vk_old_status', (string)$row['order_status']);
                    $order->set_status($status, 'A régi webshopban: '.($names[$row['order_status']] ?? $row['order_status']).'.');
                    $order->save();
                    self::count($state, 'orders_updated');
                }
                continue;
            }

            $order = new \WC_Order();
            $order->set_created_via('vk-migration');
            $order->set_currency('HUF');
            $order->set_prices_include_tax(true);
            if ($created) $order->set_date_created($created);

            $bt_row = $infos[$old]['BT'] ?? [];
            $bt = self::address($bt_row);
            $st = isset($infos[$old]['ST']) ? self::address($infos[$old]['ST']) : null;
            foreach($bt as $key => $value){
                $setter = 'set_billing_'.$key;
                if (method_exists($order, $setter)) $order->$setter($value);
            }
            foreach(($st ?: $bt) as $key => $value){
                $setter = 'set_shipping_'.$key;
                if ($key !== 'email' && method_exists($order, $setter)) $order->$setter($value);
            }
            $customer = $users[(int)$row['virtuemart_user_id']] ?? 0;
            if (!$customer && !empty($bt['email'])){
                $user = get_user_by('email', $bt['email']);
                if ($user) $customer = $user->ID;
            }
            $order->set_customer_id($customer);
            $note = self::clean(($row['customer_note'] ?? '') !== '' ? $row['customer_note'] : ($bt_row['customer_note'] ?? ''));
            if ($note !== '') $order->set_customer_note($note);
            if (!empty($row['ip_address'])) $order->set_customer_ip_address((string)$row['ip_address']);
            $tax_number = self::tax_number($bt_row);
            if ($tax_number !== '') $order->update_meta_data('_billing_tax_number', $tax_number);

            // Tételek (bruttó tételár → nettó + áfa).
            foreach($items[$old] ?? [] as $line){
                $qty = max(1, (float)$line['product_quantity']);
                $gross = (float)($line['product_subtotal_with_tax'] ?? 0);
                if ($gross <= 0) $gross = (float)($line['product_final_price'] ?? 0) * $qty;
                [ $net, $tax ] = self::split($gross);
                $item = new \WC_Order_Item_Product();
                $item->set_name(self::clean($line['order_item_name']));
                $item->set_quantity($qty);
                $product_id = $products[(int)$line['virtuemart_product_id']] ?? 0;
                $variation_id = 0;
                $sku = trim((string)($line['order_item_sku'] ?? ''));
                if ($sku !== ''){
                    $by_sku = wc_get_product_id_by_sku($sku);
                    $by_sku_product = $by_sku ? wc_get_product($by_sku) : null;
                    if ($by_sku_product && $by_sku_product->is_type('variation')){
                        $variation_id = $by_sku;
                        $product_id = $by_sku_product->get_parent_id();
                    } elseif (!$product_id && $by_sku){
                        $product_id = $by_sku;
                    }
                }
                if ($product_id){
                    $item->set_product_id($product_id);
                    if ($variation_id) $item->set_variation_id($variation_id);
                } else {
                    self::count($state, 'items_unmatched');
                }
                $item->set_subtotal($net);
                $item->set_total($net);
                $item->set_taxes(self::taxes($tax));
                if ($sku !== '') $item->add_meta_data('Cikkszám', $sku, true);
                $attribute = self::clean(preg_replace('/[{}"\[\]]+/', ' ', (string)($line['product_attribute'] ?? '')));
                if ($attribute !== '' && $attribute !== 'null') $item->add_meta_data('Változat', $attribute, true);
                $order->add_item($item);
            }

            // Szállítás, fizetési díj, kedvezmény.
            $shipment_name = $shipments[(int)($row['virtuemart_shipmentmethod_id'] ?? 0)] ?? 'Szállítás';
            $shipping_gross = (float)($row['order_shipment'] ?? 0) + (float)($row['order_shipment_tax'] ?? 0);
            $shipping = new \WC_Order_Item_Shipping();
            $shipping->set_method_title($shipment_name);
            $shipping->set_method_id('vk_old_shipping');
            [ $net, $tax ] = self::split($shipping_gross);
            $shipping->set_total($net);
            $shipping->set_taxes([ 'total' => self::taxes($tax)['total'] ]);
            $order->add_item($shipping);

            $payment_name = $payments[(int)($row['virtuemart_paymentmethod_id'] ?? 0)] ?? '';
            $order->set_payment_method('vk_old_payment');
            $order->set_payment_method_title($payment_name !== '' ? $payment_name : 'Régi webshop');
            $payment_gross = (float)($row['order_payment'] ?? 0) + (float)($row['order_payment_tax'] ?? 0);
            if (abs($payment_gross) >= 1){
                $fee = new \WC_Order_Item_Fee();
                $fee->set_name(($payment_name !== '' ? $payment_name : 'Fizetési mód').' díja');
                [ $net, $tax ] = self::split($payment_gross);
                $fee->set_total($net);
                $fee->set_taxes([ 'total' => self::taxes($tax)['total'] ]);
                $order->add_item($fee);
            }
            $discount = (float)($row['coupon_discount'] ?? 0) + (float)($row['order_discount'] ?? 0);
            if (abs($discount) >= 1){
                $fee = new \WC_Order_Item_Fee();
                $code = trim((string)($row['coupon_code'] ?? ''));
                $fee->set_name($code !== '' ? 'Kupon: '.$code : 'Kedvezmény');
                [ $net, $tax ] = self::split(-abs($discount));
                $fee->set_total($net);
                $fee->set_taxes([ 'total' => self::taxes($tax)['total'] ]);
                $order->add_item($fee);
            }

            $order->update_taxes();
            $order->calculate_totals(false);
            if ((float)$row['order_total'] > 0) $order->set_total(round((float)$row['order_total'], 2));

            if (in_array($status, [ 'processing', 'completed' ], true) && $created) $order->set_date_paid($created);
            $order->update_meta_data('_vk_old_order_id', $old);
            $order->update_meta_data('_vk_old_order_number', (string)$row['order_number']);
            $order->update_meta_data('_vk_old_status', (string)$row['order_status']);
            $order->set_status($status);
            $order_id = $order->save();
            if ($status === 'completed'){
                $done = 0;
                foreach($history[$old] ?? [] as $h) if (in_array(strtoupper($h['order_status_code']), [ 'S', 'F' ], true)) $done = self::time($h['created_on']);
                $order->set_date_completed($done ?: ($created ?: time()));
                $order->save();
            }

            // A régi állapotváltások és megjegyzések belső jegyzetként.
            $lines = [ 'Átvéve a régi webshopból (rendelésszám: '.$row['order_number'].', állapot: '.($names[$row['order_status']] ?? $row['order_status']).').' ];
            foreach($history[$old] ?? [] as $h){
                $comment = self::clean($h['comments'] ?? '');
                $lines[] = (self::time($h['created_on']) ? wp_date('Y-m-d H:i', self::time($h['created_on'])).': ' : '').($names[$h['order_status_code']] ?? $h['order_status_code']).($comment !== '' ? ' – '.$comment : '');
            }
            $order->add_order_note(implode("\n", $lines));
            self::count($state, 'orders_new');
            unset($order_id);
        }
        return count($orders) === $limit;
    }

    /* ---------- 3. Értékelések ---------- */

    private static function stage_reviews(&$state){
        if (!self::has('virtuemart_rating_reviews')) return false;
        global $wpdb;
        $limit = self::BATCH['reviews'];
        $reviews = self::rows('SELECT * FROM '.self::t('virtuemart_rating_reviews').' WHERE virtuemart_rating_review_id > '.(int)$state['last'].' ORDER BY virtuemart_rating_review_id LIMIT '.$limit);
        if (!$reviews) return false;
        $products = self::product_map();
        $users = self::user_map();
        $votes = [];
        if (self::has('virtuemart_rating_votes')){
            foreach(self::rows('SELECT virtuemart_product_id, created_by, vote FROM '.self::t('virtuemart_rating_votes')) as $v) $votes[(int)$v['virtuemart_product_id'].'-'.(int)$v['created_by']] = (int)$v['vote'];
        }
        $names = [];
        $authors = array_filter(array_map(function($r){ return (int)$r['created_by']; }, $reviews));
        if ($authors && self::has('users')){
            foreach(self::rows('SELECT id, name, email FROM '.self::t('users').' WHERE id IN ('.implode(',', $authors).')') as $u) $names[(int)$u['id']] = $u;
        }
        $touched = [];
        foreach($reviews as $review){
            $old = (int)$review['virtuemart_rating_review_id'];
            $state['last'] = $old;
            $product_id = $products[(int)$review['virtuemart_product_id']] ?? 0;
            $text = self::clean($review['comment'] ?? '');
            if (!$product_id || $text === ''){
                self::count($state, 'reviews_unmatched');
                continue;
            }
            if ($wpdb->get_var($wpdb->prepare("SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key = '_vk_old_review_id' AND meta_value = %s LIMIT 1", (string)$old))) continue;
            $author = $names[(int)$review['created_by']] ?? [];
            $rating = (int)round((float)($review['review_rating'] ?? 0));
            if ($rating < 1) $rating = $votes[(int)$review['virtuemart_product_id'].'-'.(int)$review['created_by']] ?? 0;
            $created = self::time($review['created_on'] ?? '') ?: time();
            $comment_id = wp_insert_comment([
                'comment_post_ID' => $product_id,
                'comment_author' => self::clean($author['name'] ?? '') ?: 'Vásárló',
                'comment_author_email' => sanitize_email($author['email'] ?? ''),
                'comment_content' => $text,
                'comment_type' => 'review',
                'comment_approved' => (int)($review['published'] ?? 1) ? 1 : 0,
                'user_id' => $users[(int)$review['created_by']] ?? 0,
                'comment_date' => wp_date('Y-m-d H:i:s', $created),
                'comment_date_gmt' => gmdate('Y-m-d H:i:s', $created),
            ]);
            if (!$comment_id) continue;
            if ($rating >= 1) add_comment_meta($comment_id, 'rating', min(5, $rating), true);
            add_comment_meta($comment_id, '_vk_old_review_id', $old, true);
            $touched[$product_id] = true;
            self::count($state, 'reviews_new');
        }
        foreach(array_keys($touched) as $product_id) \WC_Comments::clear_transients($product_id);
        return count($reviews) === $limit;
    }

    /* ---------- 4. Kuponok ---------- */

    private static function stage_coupons(&$state){
        global $wpdb;
        if (!self::has('virtuemart_coupons')) return false;
        foreach(self::rows('SELECT * FROM '.self::t('virtuemart_coupons').' ORDER BY virtuemart_coupon_id') as $row){
            $code = wc_format_coupon_code((string)$row['coupon_code']);
            // Bármilyen állapotú (a lejárt piszkozatként kerül át) kupon ezzel a kóddal: már megvan.
            if ($code === '' || $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_status <> 'trash' AND post_title = %s LIMIT 1", $code))) continue;
            $expires = self::time($row['coupon_expiry_date'] ?? '');
            $coupon = new \WC_Coupon();
            $coupon->set_code($code);
            $coupon->set_discount_type(($row['percent_or_total'] ?? '') === 'percent' ? 'percent' : 'fixed_cart');
            $coupon->set_amount((float)$row['coupon_value']);
            if ($expires) $coupon->set_date_expires($expires);
            if ((float)($row['coupon_value_valid'] ?? 0) > 0) $coupon->set_minimum_amount((float)$row['coupon_value_valid']);
            if (($row['coupon_type'] ?? '') === 'gift') $coupon->set_usage_limit(1);
            $coupon->set_description('A régi webshopból.');
            $coupon->set_status((int)($row['published'] ?? 1) && (!$expires || $expires > time()) ? 'publish' : 'draft');
            $coupon->update_meta_data('_vk_old_coupon_id', (int)$row['virtuemart_coupon_id']);
            $coupon->save();
            self::count($state, 'coupons_new');
        }
        return false;
    }

    /* ---------- 5. Archívum: minden régi tábla CSV-ben ---------- */

    /** A védett archívum-mappa (a webről nem érhető el; letöltés az adminból). */
    static function archive_dir(){
        $dir = trailingslashit(wp_upload_dir()['basedir']).'vk-regi-adatok-'.substr(wp_hash('vk-regi-adatok'), 0, 12);
        if (!is_dir($dir)){
            wp_mkdir_p($dir);
            file_put_contents($dir.'/.htaccess', "Require all denied\nDeny from all\n");
            file_put_contents($dir.'/index.php', "<?php // Silence is golden.\n");
        }
        return $dir;
    }

    static function archive_files(){
        $dir = self::archive_dir();
        return array_values(array_filter((array)glob($dir.'/*.csv'), 'is_file'));
    }

    private static function stage_archive(&$state){
        global $wpdb;
        $tables = array_values(array_filter(self::tables(), function($t){ return !in_array($t, self::SKIP_TABLES, true); }));
        $dir = self::archive_dir();
        if ((int)$state['table'] === 0 && (int)$state['offset'] === 0){
            foreach(self::archive_files() as $file) @unlink($file);
            self::subscribers($state);
        }
        $table = $tables[(int)$state['table']] ?? null;
        if ($table === null) return false;

        $columns = array_values(array_filter(self::columns($table), function($c){ return !in_array(strtolower($c), self::SECRET_COLUMNS, true); }));
        $file = $dir.'/'.sanitize_file_name($table).'.csv';
        $limit = self::BATCH['archive'];
        $rows = $columns ? self::rows('SELECT `'.implode('`, `', $columns).'` FROM '.self::t($table).' LIMIT '.(int)$state['offset'].', '.$limit) : [];
        $fh = fopen($file, 'a');
        if ((int)$state['offset'] === 0){
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, $columns, ';');
            self::count($state, 'archive_tables');
        }
        foreach($rows as $row) fputcsv($fh, array_values($row), ';');
        fclose($fh);
        self::count($state, 'archive_rows', count($rows));

        if (count($rows) === $limit){
            $state['offset'] += $limit;
        } else {
            $state['table']++;
            $state['offset'] = 0;
        }
        return $state['table'] < count($tables);
    }

    /**
     * Hírlevél-feliratkozók a régi oldal ismert hírlevél-bővítményeiből (AcyMailing, jNews, ccNewsletter, egyéb
     * „subscri…” / „newsletter” tábla e-mail oszloppal): feliratkozok.csv, és a vásárlói fiókon _vk_newsletter jelölés.
     */
    private static function subscribers(&$state){
        $list = [];
        foreach(self::tables() as $table){
            if (!preg_match('/subscri|newsletter|acym_user|acymailing_subscriber/i', $table)) continue;
            $columns = self::columns($table);
            if (!in_array('email', $columns, true)) continue;
            $select = [ '`email`' ];
            foreach([ 'name', 'created', 'creation_date', 'confirmed', 'enabled', 'active', 'accept' ] as $c) if (in_array($c, $columns, true)) $select[] = '`'.$c.'`';
            foreach(self::rows('SELECT '.implode(', ', $select).' FROM '.self::t($table)) as $row){
                $email = sanitize_email(strtolower(trim((string)$row['email'])));
                if (!is_email($email)) continue;
                $date = $row['created'] ?? ($row['creation_date'] ?? '');
                if (is_numeric($date)) $date = gmdate('Y-m-d H:i:s', (int)$date);
                $active = !isset($row['enabled']) && !isset($row['active']) ? 1 : (int)($row['enabled'] ?? $row['active']);
                $list[$email] = [ $email, self::clean($row['name'] ?? ''), (string)$date, (int)($row['confirmed'] ?? 1), $active, $table ];
            }
        }
        if (!$list) return;
        $fh = fopen(self::archive_dir().'/feliratkozok.csv', 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, [ 'email', 'nev', 'feliratkozas', 'megerositve', 'aktiv', 'forras' ], ';');
        foreach($list as $row){
            fputcsv($fh, $row, ';');
            $user = get_user_by('email', $row[0]);
            if ($user && $row[4]) update_user_meta($user->ID, '_vk_newsletter', 1);
        }
        fclose($fh);
        self::count($state, 'subscribers', count($list));
    }

    /** Az archívum letöltése ZIP-ben (csak boltkezelőnek). */
    static function download(){
        if (!current_user_can('manage_woocommerce') || !check_admin_referer('vk_mig_data_download')) wp_die('', '', [ 'response' => 403 ]);
        $files = self::archive_files();
        if (!$files || !class_exists('ZipArchive')) wp_die('Nincs még archívum (vagy a szerveren nincs ZIP-támogatás).');
        $zip_file = wp_tempnam('vk-regi-adatok.zip');
        $zip = new \ZipArchive();
        $zip->open($zip_file, \ZipArchive::OVERWRITE);
        foreach($files as $file) $zip->addFile($file, 'regi-webshop-adatok/'.basename($file));
        $zip->close();
        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="vasskisgep-regi-adatok-'.gmdate('Ymd').'.zip"');
        header('Content-Length: '.filesize($zip_file));
        readfile($zip_file);
        @unlink($zip_file);
        exit;
    }

    /* ---------- Felület (a Régi webshop átköltöztetés oldalán) ---------- */

    static function card(){
        $settings = self::settings();
        $state = self::state();
        $prefix = self::prefix();
        echo '<div class="card"><h2>Régi adatok: vásárlók, rendelések, értékelések, kuponok, archívum</h2>';
        echo '<p>A régi webshop adatbázisából átveszi a vásárlói fiókokat (címekkel; a régi jelszavukkal be tudnak lépni), a rendeléseket (a régi rendelésszámmal, tételekkel, szállítással, állapottal – a vásárló a fiókjában is látja), a termékértékeléseket és a kuponokat; minden régi táblát (hírlevél-feliratkozók, űrlapküldések, minden egyéb) CSV-archívumba ment. Levél nem megy ki, a készlet nem változik. Újrafuttatható: az átállás napján a friss mentéssel csak az újak kerülnek át, a régi rendelések állapota frissül.</p>';
        echo '<p><strong>Előkészítés:</strong> a régi Joomla-adatbázis exportja (phpMyAdmin → Exportálás, SQL) importálva ebbe az adatbázisba (a táblák előtagja más, nem ütköznek a WordPress tábláival) – vagy ha ugyanazon a szerveren van és a WordPress felhasználója olvashatja, elég az adatbázis nevét megadni.</p>';
        echo '<p><label>Régi adatbázis neve (ha másik): <input class="vk-mig-setting" name="data_db" value="'.esc_attr($settings['db']).'" placeholder="(ugyanez)"></label> &nbsp; <label>Tábla-előtag: <input class="vk-mig-setting" name="data_prefix" value="'.esc_attr($settings['prefix']).'" placeholder="'.esc_attr($prefix !== '' ? $prefix.' (megtalálva)' : 'pl. jos_').'"></label> <button class="button vk-mig-save">Mentés</button></p>';
        if ($prefix !== ''){
            $counts = [];
            foreach(self::source_counts() as $label => $n) $counts[] = number_format_i18n($n).' '.$label;
            echo '<p>Forrás: <code>'.esc_html(($settings['db'] !== '' ? $settings['db'].'.' : '').$prefix).'*</code> – '.esc_html(implode(', ', $counts)).'.</p>';
        } else {
            $found = self::detect();
            echo '<p><em>'.esc_html($found ? 'Több előtag van: '.implode(', ', $found).' – add meg, melyiket.' : 'A régi webshop táblái (…virtuemart_orders) még nincsenek az adatbázisban.').'</em></p>';
        }
        if (!empty($state['started'])) echo '<p style="white-space:pre-line">'.esc_html(self::message($state)).(!empty($state['finished']) ? ' ('.esc_html(wp_date('Y-m-d H:i', $state['finished'])).')' : '').'</p>';
        echo '<p><button class="button button-primary vk-mig-run" data-action="data" data-reset="1"'.disabled($prefix === '', true, false).'>Átvétel indítása</button>';
        if (!empty($state['started']) && empty($state['finished'])) echo ' <button class="button vk-mig-run" data-action="data">Folytatás</button>';
        if (self::archive_files()) echo ' <a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=vk_mig_data_download'), 'vk_mig_data_download')).'">Archívum letöltése (ZIP)</a>';
        echo '</p><div class="vk-mig-progress"></div><div class="vk-mig-log" hidden></div></div>';
    }
}
