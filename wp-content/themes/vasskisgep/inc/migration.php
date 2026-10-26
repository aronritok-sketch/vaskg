<?php

namespace VasskisgepTheme;

require_once(__DIR__.'/migration-source.php');
require_once(__DIR__.'/migration-classifier.php');
require_once(__DIR__.'/migration-importer.php');
require_once(__DIR__.'/migration-stock.php');
require_once(__DIR__.'/migration-related.php');

/**
 * Ideiglenes átköltöztető a régi (Joomla + VirtueMart) webshopból: Termékek → Régi webshop átköltöztetés.
 *
 *  1. Beolvasás: a régi bolt nyilvános oldalairól (MigrationSource) átmeneti rekordokba.
 *  2. Besorolás: a régi kategóriaútvonal → új kategória megfeleltetés (setup/migration/category-map.json);
 *     a vegyes régi kategóriák termékeit Claude sorolja be (MigrationClassifier), a bizonytalanok kézi
 *     ellenőrzésre kerülnek.
 *  3. Áttekintés: táblázat, a kategória módosítható, jóváhagyás / kihagyás.
 *  4. Import (MigrationImporter): WooCommerce termékek képekkel, dokumentumokkal, műszaki adatokkal;
 *     a régi címekről 301-es átirányítás.
 *  5. Lezárás: az átmeneti rekordok törlése (az átirányítások megmaradnak).
 *
 * Rekord állapotok (_vk_mig_status): new, check (Claude-ra vár), review (kézi ellenőrzés), ready
 * (jóváhagyva), imported, skipped.
 */
class Migration {
    const CPT = 'vk_old_product';
    const OPTION = 'vk_migration';
    const REDIRECTS = 'vk_mig_redirects';
    const PAGE = 'vk-migration';

    const STATUSES = [
        'new' => 'Új',
        'check' => 'Claude-ra vár',
        'review' => 'Ellenőrizendő',
        'ready' => 'Jóváhagyva',
        'imported' => 'Importálva',
        'skipped' => 'Kihagyva',
    ];

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('init', [$this, 'register']);
        add_action('admin_menu', [$this, 'menu'], 60);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        foreach([ 'crawl', 'map', 'ai', 'import', 'set', 'save', 'upload', 'stock', 'related', 'close' ] as $action){
            add_action('wp_ajax_vk_mig_'.$action, [$this, 'ajax_'.$action]);
        }
        add_action('admin_post_vk_mig_export', [$this, 'export']);
        MigrationRelated::init();
        // A régi címek átirányítása (a lezárás után is).
        add_action('template_redirect', [$this, 'redirect'], 4);

        if (defined('WP_CLI') && WP_CLI) \WP_CLI::add_command('vasskisgep migrate', [$this, 'cli']);
    }

    static function settings(){
        return wp_parse_args((array)get_option(self::OPTION, []), [
            'source' => 'https://vasskisgep.hu',
            'api_key' => '',
            'model' => 'claude-sonnet-5',
            'threshold' => 0.8,
            'status' => 'publish',
            'images' => 'all',
            'closed' => 0,
        ]);
    }

    function register(){
        register_post_type(self::CPT, [
            'label' => 'Régi webshop termékek',
            'public' => false,
            'show_ui' => false,
            'rewrite' => false,
            'query_var' => false,
            'supports' => [ 'title' ],
        ]);
    }

    /* ---------- Rekordok ---------- */

    static function find($old_id){
        $posts = get_posts([ 'post_type' => self::CPT, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_vk_old_id', 'meta_value' => (int)$old_id ]);
        return $posts ? (int)$posts[0] : 0;
    }

    static function data($post_id){
        $data = json_decode((string)get_post_meta($post_id, '_vk_old', true), true);
        return is_array($data) ? $data : [];
    }

    static function status($post_id){
        return (string)get_post_meta($post_id, '_vk_mig_status', true) ?: 'new';
    }

    static function suggestion($post_id){
        $s = json_decode((string)get_post_meta($post_id, '_vk_mig_suggestion', true), true);
        return is_array($s) ? $s : [];
    }

    static function set_suggestion($post_id, array $suggestion, $status){
        update_post_meta($post_id, '_vk_mig_suggestion', wp_slash(wp_json_encode($suggestion, JSON_UNESCAPED_UNICODE)));
        update_post_meta($post_id, '_vk_mig_status', $status);
    }

    /** Beolvasott termék mentése / frissítése (a besorolás megmarad). */
    static function upsert(array $data){
        $id = self::find($data['id']);
        // Ugyanaz a termék több régi címen (pl. „Kiemelt ajánlatunk” alatt is): a pontosabb (mélyebb)
        // kategóriaútvonal marad, és minden régi cím megmarad az átirányításhoz.
        $paths = array_filter([ $data['path_url'] ?? '' ]);
        if ($id){
            $old = self::data($id);
            $paths = array_merge((array)($old['all_paths'] ?? []), array_filter([ $old['path_url'] ?? '' ]), $paths);
            if (count((array)($old['path'] ?? [])) > count((array)($data['path'] ?? []))){
                $data['path'] = $old['path'];
                $data['path_url'] = $old['path_url'];
            }
        }
        $data['all_paths'] = array_values(array_unique(array_merge($paths, (array)($data['all_paths'] ?? []))));
        $post = [ 'post_type' => self::CPT, 'post_status' => 'private', 'post_title' => wp_slash($data['name']), 'post_name' => 'vm-'.(int)$data['id'] ];
        if ($id){
            $post['ID'] = $id;
            wp_update_post($post);
        } else {
            $id = wp_insert_post($post);
            if (!$id || is_wp_error($id)) return 0;
            update_post_meta($id, '_vk_old_id', (int)$data['id']);
            update_post_meta($id, '_vk_mig_status', 'new');
        }
        update_post_meta($id, '_vk_old', wp_slash(wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
        return $id;
    }

    static function ids($status = null, $limit = -1){
        $args = [ 'post_type' => self::CPT, 'post_status' => 'any', 'numberposts' => $limit, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ];
        if ($status !== null) $args['meta_query'] = [[ 'key' => '_vk_mig_status', 'value' => (array)$status, 'compare' => 'IN' ]];
        return array_map('intval', get_posts($args));
    }

    static function counts(){
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT m.meta_value AS s, COUNT(*) AS n FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_vk_mig_status' WHERE p.post_type = %s GROUP BY m.meta_value", self::CPT));
        $counts = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach($rows as $row) $counts[$row->s] = (int)$row->n;
        $counts['total'] = array_sum($counts);
        return $counts;
    }

    /* ---------- Admin ---------- */

    function menu(){
        add_submenu_page('edit.php?post_type=product', 'Régi webshop átköltöztetés', 'Régi webshop átköltöztetés', 'manage_woocommerce', self::PAGE, [$this, 'page']);
    }

    function assets($hook){
        if (strpos((string)$hook, self::PAGE) === false) return;
        $dir = get_stylesheet_directory();
        $uri = get_stylesheet_directory_uri();
        wp_enqueue_script('vasskisgep-migration', $uri.'/assets/migration.js', ['jquery'], filemtime($dir.'/assets/migration.js'), true);
        wp_localize_script('vasskisgep-migration', 'vkMigration', [ 'ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('vk_mig') ]);
    }

    private function check(){
        if (!current_user_can('manage_woocommerce') || !check_ajax_referer('vk_mig', 'nonce', false)) wp_send_json_error([ 'message' => 'Nincs jogosultság.' ], 403);
        @set_time_limit(120);
    }

    function ajax_crawl(){
        $this->check();
        if (!empty($_POST['reset'])) MigrationSource::reset();
        wp_send_json_success(MigrationSource::step());
    }

    function ajax_map(){
        $this->check();
        wp_send_json_success(MigrationClassifier::apply_map(400));
    }

    function ajax_ai(){
        $this->check();
        $result = MigrationClassifier::ai_step();
        if (is_wp_error($result)) wp_send_json_error([ 'message' => $result->get_error_message(), 'retry' => (int)($result->get_error_data()['retry_after'] ?? 0) ]);
        wp_send_json_success($result);
    }

    function ajax_import(){
        $this->check();
        wp_send_json_success(MigrationImporter::step());
    }

    /** Áttekintés: kategória módosítása, jóváhagyás, kihagyás. */
    function ajax_set(){
        $this->check();
        $ids = array_map('absint', (array)($_POST['ids'] ?? []));
        $action = sanitize_key($_POST['todo'] ?? '');
        $term = absint($_POST['term'] ?? 0);
        $done = 0;
        foreach($ids as $id){
            if (get_post_type($id) !== self::CPT || self::status($id) === 'imported') continue;
            $suggestion = self::suggestion($id);
            if ($term && get_term($term, 'product_cat')){
                $suggestion['term'] = $term;
                $suggestion['source'] = 'kézi';
                $suggestion['confidence'] = 1;
            }
            if ($action === 'skip') $status = 'skipped';
            elseif ($action === 'approve' && !empty($suggestion['term'])) $status = 'ready';
            elseif ($action === 'claude') $status = 'check';
            else $status = self::status($id) === 'skipped' ? 'review' : self::status($id);
            if ($term && $action === '') $status = 'ready';
            self::set_suggestion($id, $suggestion, $status);
            $done++;
        }
        wp_send_json_success([ 'message' => $done.' tétel frissítve', 'counts' => self::counts() ]);
    }

    function ajax_save(){
        $this->check();
        $settings = self::settings();
        foreach([ 'source', 'model', 'status', 'images' ] as $key){
            if (isset($_POST[$key])) $settings[$key] = sanitize_text_field(wp_unslash($_POST[$key]));
        }
        if (isset($_POST['threshold'])) $settings['threshold'] = max(0.5, min(1, (float)$_POST['threshold']));
        if (!empty($_POST['api_key'])) $settings['api_key'] = sanitize_text_field(wp_unslash($_POST['api_key']));
        if (!empty($_POST['forget_key'])) $settings['api_key'] = '';
        update_option(self::OPTION, $settings, false);
        wp_send_json_success([ 'message' => 'Beállítások mentve.' ]);
    }

    /** Beolvasás fájlból (ha a szerver nem éri el a régi boltot): a regi-webshop.json. */
    function ajax_upload(){
        $this->check();
        if (empty($_FILES['file']['tmp_name'])) wp_send_json_error([ 'message' => 'Nincs fájl.' ]);
        $items = json_decode((string)file_get_contents($_FILES['file']['tmp_name']), true);
        if (!is_array($items)) wp_send_json_error([ 'message' => 'A fájl nem érvényes JSON.' ]);
        $count = 0;
        foreach($items as $item){
            if (!empty($item['id']) && !empty($item['name']) && self::upsert($item)) $count++;
        }
        wp_send_json_success([ 'message' => $count.' termék betöltve a fájlból.', 'done' => true ]);
    }

    /** Kapcsolódó termékek a régi boltból (MigrationRelated). */
    function ajax_related(){
        $this->check();
        if (!empty($_POST['reset'])) MigrationRelated::reset();
        $result = MigrationRelated::step();
        if (!empty($result['locked'])) sleep(5);
        wp_send_json_success($result);
    }

    /** Készlet CSV feltöltése (MigrationStock). */
    function ajax_stock(){
        $this->check();
        if (empty($_FILES['file']['tmp_name'])) wp_send_json_error([ 'message' => 'Nincs fájl.' ]);
        $rows = MigrationStock::parse($_FILES['file']['tmp_name']);
        if (is_wp_error($rows)) wp_send_json_error([ 'message' => $rows->get_error_message() ]);
        wp_send_json_success([ 'message' => MigrationStock::message(MigrationStock::load($rows)), 'done' => true ]);
    }

    /** A beolvasott adatok letöltése JSON-ként (egy másik telepítésbe feltölthető). */
    function export(){
        if (!current_user_can('manage_woocommerce') || !check_admin_referer('vk_mig_export')) wp_die('', '', [ 'response' => 403 ]);
        $items = [];
        foreach(self::ids() as $id) $items[] = self::data($id);
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="regi-webshop-'.gmdate('Ymd').'.json"');
        echo wp_json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Lezárás: az átmeneti rekordok törlése adagokban (az átirányítások maradnak). */
    function ajax_close(){
        $this->check();
        $ids = self::ids(null, 200);
        foreach($ids as $id) wp_delete_post($id, true);
        $left = count(self::ids(null, 1));
        if (!$left){
            $settings = self::settings();
            $settings['closed'] = time();
            $settings['api_key'] = '';
            update_option(self::OPTION, $settings, false);
            delete_option(MigrationSource::STATE);
        }
        wp_send_json_success([ 'done' => !$left, 'message' => $left ? 'Törlés folyamatban…' : 'Az átköltöztetés lezárva. Az átmeneti adatok és az API-kulcs törölve, a régi címek átirányítása működik.' ]);
    }

    /* ---------- Átirányítás a régi címekről ---------- */

    static function redirects(){
        return wp_parse_args((array)get_option(self::REDIRECTS, []), [ 'products' => [], 'categories' => [] ]);
    }

    static function add_redirect($type, $path, $target){
        $map = self::redirects();
        $map[$type][self::normalize_path($path)] = (int)$target;
        update_option(self::REDIRECTS, $map, false);
    }

    static function normalize_path($path){
        $path = rawurldecode((string)parse_url($path, PHP_URL_PATH));
        return trim(mb_strtolower($path), '/');
    }

    function redirect(){
        if (!is_404()) return;
        $home = trim((string)parse_url(home_url('/'), PHP_URL_PATH), '/');
        $path = self::normalize_path($_SERVER['REQUEST_URI'] ?? '');
        if ($home !== '' && strpos($path, $home.'/') === 0) $path = substr($path, strlen($home) + 1);
        if (strpos($path, 'termekek/') !== 0) return;

        $map = self::redirects();
        $url = '';
        if (!empty($map['products'][$path])){
            $url = get_permalink($map['products'][$path]);
        } else {
            // Kategória: a leghosszabb ismert előtag.
            for ($try = $path; $try !== '' && $try !== 'termekek'; $try = dirname($try)){
                if (!empty($map['categories'][$try])){
                    $term = get_term($map['categories'][$try]);
                    if ($term && !is_wp_error($term)) $url = get_term_link($term);
                    break;
                }
            }
        }
        if (!$url || is_wp_error($url)) return;
        wp_safe_redirect($url, 301);
        exit;
    }

    /* ---------- Felület ---------- */

    function page(){
        $settings = self::settings();
        $counts = self::counts();
        $crawl = MigrationSource::state();
        $key = MigrationClassifier::key();
        echo '<div class="wrap vk-mig"><h1>Régi webshop átköltöztetés</h1>';
        echo '<style>.vk-mig .card{max-width:none;margin:1rem 0;padding:1rem 1.4rem}.vk-mig .vk-mig-progress{margin:.6rem 0;font-weight:600}.vk-mig .vk-mig-log{max-height:9rem;overflow:auto;background:#f6f7f7;padding:.4rem .6rem;font:12px/1.5 monospace;white-space:pre-wrap}.vk-mig table.widefat td{vertical-align:middle}.vk-mig .vk-badge{display:inline-block;padding:.1rem .45rem;border-radius:1rem;background:#f0f0f1;font-size:11px}.vk-mig .vk-badge.review{background:#fcf0e3}.vk-mig .vk-badge.ready{background:#e7f5ea}.vk-mig .vk-badge.imported{background:#dfe9f6}.vk-mig .vk-mig-counts span{margin-right:1rem}.vk-mig select.vk-mig-term{max-width:24rem}</style>';

        if ($settings['closed']){
            echo '<div class="notice notice-success"><p>Az átköltöztetés '.esc_html(wp_date('Y-m-d H:i', $settings['closed'])).' óta lezárva. A régi webshop címei átirányítódnak az új termékekre ('.count(self::redirects()['products']).' termék, '.count(self::redirects()['categories']).' kategória).</p></div>';
        }

        echo '<p class="vk-mig-counts">';
        foreach(self::STATUSES as $status => $label) echo '<span>'.esc_html($label).': <strong>'.(int)$counts[$status].'</strong></span>';
        echo '<span>Összesen: <strong>'.(int)$counts['total'].'</strong></span></p>';

        // 1. Beolvasás
        echo '<div class="card"><h2>1. Beolvasás a régi webshopból</h2>';
        echo '<p>A régi bolt nyilvános oldalait járja be (kategóriák, termékoldalak) – a régi oldalon semmit nem módosít. Újrafuttatható: a már beolvasott termékek adatai frissülnek, a besorolásuk megmarad.</p>';
        echo '<p><label>Régi webshop címe: <input type="url" class="regular-text vk-mig-setting" name="source" value="'.esc_attr($settings['source']).'"></label></p>';
        // A domain átállítása után a régi bolt már nem érhető el ezen a címen: addig kell beolvasni.
        $source_host = preg_replace('/^www\./', '', (string)parse_url($settings['source'], PHP_URL_HOST));
        $site_host = preg_replace('/^www\./', '', (string)parse_url(home_url(), PHP_URL_HOST));
        if ($source_host !== '' && $source_host === $site_host){
            echo '<div class="notice notice-warning inline"><p>A megadott cím ennek az oldalnak a címe: a domain átállítása után a régi bolt már nem olvasható be innen. A beolvasást és az importot az átállítás előtt kell elvégezni (vagy a régi bolt ideiglenes címét megadni).</p></div>';
        }
        echo '<p><button class="button button-primary vk-mig-run" data-action="crawl" data-reset="1">Beolvasás indítása</button> ';
        if (!empty($crawl['started']) && empty($crawl['finished'])) echo '<button class="button vk-mig-run" data-action="crawl">Folytatás</button> ';
        echo '<label class="button">JSON fájl betöltése<input type="file" class="vk-mig-upload" accept=".json,application/json" hidden></label>';
        if ($counts['total']) echo ' <a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=vk_mig_export'), 'vk_mig_export')).'">Letöltés JSON-ként</a>';
        echo '</p>';
        if (!empty($crawl['finished'])) echo '<p>Utolsó beolvasás: '.esc_html(wp_date('Y-m-d H:i', $crawl['finished'])).', '.(int)$crawl['products'].' termékoldal, '.count((array)$crawl['errors']).' hiba.</p>';
        echo '<div class="vk-mig-progress"></div><div class="vk-mig-log" hidden></div></div>';

        // 2. Besorolás
        echo '<div class="card"><h2>2. Besorolás az új kategóriafába</h2>';
        echo '<p>A régi kategóriák többsége egyértelműen megfeleltethető (<code>setup/migration/category-map.json</code>). A vegyes régi kategóriák termékeit (pl. „Akkumulátoros fűrészek”: gépek és tartozékok) Claude sorolja be a név, a régi kategória, a műszaki adatok és a leírás alapján; ha nem elég biztos ('.esc_html(number_format_i18n($settings['threshold'] * 100)).'% alatt), a termék ellenőrizendő lesz.</p>';
        echo '<p><button class="button button-primary vk-mig-run" data-action="map">Besorolás a megfeleltetés alapján</button></p>';
        echo '<p><strong>Claude API:</strong> '.($key !== '' ? 'kulcs beállítva'.(defined('VASSKISGEP_ANTHROPIC_API_KEY') ? ' (wp-config.php)' : '') : 'nincs kulcs – a Claude-ra váró termékek kézi ellenőrzésre kerülnek').'. ';
        if (!defined('VASSKISGEP_ANTHROPIC_API_KEY')) echo '<input type="password" class="regular-text vk-mig-setting" name="api_key" placeholder="'.($key !== '' ? 'Új kulcs megadása' : 'sk-ant-…').'" autocomplete="off"> ';
        echo 'Modell: <input type="text" class="vk-mig-setting" name="model" value="'.esc_attr($settings['model']).'" size="16"> ';
        echo 'Küszöb: <input type="number" class="vk-mig-setting" name="threshold" value="'.esc_attr($settings['threshold']).'" min="0.5" max="1" step="0.05" style="width:5rem"> ';
        echo '<button class="button vk-mig-save">Mentés</button>'.($key !== '' && !defined('VASSKISGEP_ANTHROPIC_API_KEY') ? ' <button class="button-link vk-mig-save" data-forget="1">kulcs törlése</button>' : '').'</p>';
        $estimate = MigrationClassifier::estimate($counts['check']);
        echo '<p><button class="button button-primary vk-mig-run" data-action="ai"'.($key === '' ? ' disabled' : '').'>Claude besorolás ('.(int)$counts['check'].' termék)</button> <span class="description">Becsült költség: ~'.esc_html($estimate).' USD. A kulcs csak az átköltöztetés idejére kell; lezáráskor törlődik.</span></p>';
        echo '<div class="vk-mig-progress"></div><div class="vk-mig-log" hidden></div></div>';

        // 3. Áttekintés
        echo '<div class="card"><h2>3. Áttekintés</h2>';
        $this->review_table();
        echo '</div>';

        // 4. Import
        echo '<div class="card"><h2>4. Import a webshopba</h2>';
        echo '<p>A jóváhagyott termékek WooCommerce termékként jönnek létre (a régi azonosító alapján újrafuttatáskor frissülnek): név, cikkszám, ár és akciós ár, készletállapot (a „visszaigazolástól 1-5 munkanap” utánrendelhető), márka, kategória, műszaki adatok (szűrők), leírás, képek, dokumentumok. A régi termék- és kategóriacímek 301-gyel az újakra irányítanak.</p>';
        echo '<p><label>Állapot: <select class="vk-mig-setting" name="status"><option value="publish"'.selected($settings['status'], 'publish', false).'>Közzétéve</option><option value="draft"'.selected($settings['status'], 'draft', false).'>Piszkozat</option></select></label> ';
        echo '<label>Képek: <select class="vk-mig-setting" name="images"><option value="all"'.selected($settings['images'], 'all', false).'>Minden kép</option><option value="main"'.selected($settings['images'], 'main', false).'>Csak főkép</option><option value="none"'.selected($settings['images'], 'none', false).'>Nincs (gyors próba)</option></select></label> ';
        echo '<button class="button vk-mig-save">Mentés</button></p>';
        echo '<p><button class="button button-primary vk-mig-run" data-action="import">Import indítása ('.(int)$counts['ready'].' jóváhagyott termék)</button> <span class="description">A bemutató (DEMO-) termékeket az import előtt törölni érdemes: Termékek → keresés: DEMO-.</span></p>';
        echo '<div class="vk-mig-progress"></div><div class="vk-mig-log" hidden></div></div>';

        // Készlet (CSV)
        $stock = MigrationStock::stored();
        echo '<div class="card"><h2>Készlet (CSV)</h2>';
        echo '<p>A régi bolt nyilvános oldalain csak a készletállapot látszik, a darabszám a régi adatbázisban van. A tárhely phpMyAdmin felületén futtasd ezt a lekérdezést (a tábla előtagja eltérhet, pl. <code>jos_</code>), majd az eredményt exportáld CSV-be, <em>oszlopnevekkel az első sorban</em>:</p>';
        echo '<p><code>SELECT virtuemart_product_id, product_sku, product_in_stock, product_ordered FROM xxx_virtuemart_products;</code></p>';
        echo '<p>Más forrásból (raktárprogram, Excel) is jó: pontosvesszővel vagy vesszővel elválasztott CSV, első sorban <code>cikkszám;készlet</code> (vagy <code>virtuemart_product_id</code>). A már importált termékek készlete azonnal frissül, a többié az importkor. Elfogyott termék rendelhető marad („visszaigazolástól 1-5 munkanap”). Újra feltölthető (élesítés napján a friss készlettel).</p>';
        echo '<p><label class="button button-primary">Készlet CSV betöltése<input type="file" class="vk-mig-upload" data-action="stock" accept=".csv,text/csv,text/plain" hidden></label>';
        if ($stock['updated']) echo ' <span class="description">Utolsó betöltés: '.esc_html(wp_date('Y-m-d H:i', $stock['updated'])).', '.(int)max(count($stock['id']), count($stock['sku'])).' tétel.</span>';
        echo '</p></div>';

        // Kapcsolódó termékek
        $related = MigrationRelated::state();
        echo '<div class="card"><h2>Kapcsolódó termékek a régi boltból</h2>';
        echo '<p>A régi termékoldalak „Kapcsolódó termékek” sávja (olajok, kerekek, tartozékok) a termék keresztértékesítése lesz: a termékoldalon jobb oldalt, kosárba gombbal jelenik meg. Ahol már van keresztértékesítés (kézzel beállítva), az marad. A háttérben magától is lefut; a régi boltnak elérhetőnek kell lennie (a domain átállítása előtt).</p>';
        if (!empty($related['started'])) echo '<p>'.(!empty($related['finished']) ? 'Kész ('.esc_html(wp_date('Y-m-d H:i', $related['finished'])).'): ' : 'Folyamatban: ').esc_html(MigrationRelated::message($related)).'</p>';
        echo '<p><button class="button button-primary vk-mig-run" data-action="related" data-reset="1">Átvétel indítása</button>';
        if (!empty($related['started']) && empty($related['finished'])) echo ' <button class="button vk-mig-run" data-action="related">Folytatás</button>';
        echo '</p><div class="vk-mig-progress"></div><div class="vk-mig-log" hidden></div></div>';

        // 5. Lezárás
        echo '<div class="card"><h2>5. Lezárás</h2><p>Ha minden termék átkerült: az átmeneti adatok és az API-kulcs törlése. Az átirányítások megmaradnak.</p>';
        echo '<p><button class="button vk-mig-run" data-action="close" data-confirm="Biztosan lezárod? Az átmeneti adatok törlődnek.">Átköltöztetés lezárása</button></p><div class="vk-mig-progress"></div></div>';
        echo '</div>';
    }

    private function review_table(){
        $filter = sanitize_key($_GET['vk_status'] ?? 'review');
        $search = sanitize_text_field(wp_unslash($_GET['vk_s'] ?? ''));
        $paged = max(1, absint($_GET['vk_page'] ?? 1));
        $per_page = 50;
        $args = [ 'post_type' => self::CPT, 'post_status' => 'any', 'posts_per_page' => $per_page, 'paged' => $paged, 'orderby' => 'title', 'order' => 'ASC' ];
        if ($filter !== 'all') $args['meta_query'] = [[ 'key' => '_vk_mig_status', 'value' => $filter ]];
        if ($search !== '') $args['s'] = $search;
        $query = new \WP_Query($args);

        $base = admin_url('edit.php?post_type=product&page='.self::PAGE);
        echo '<form method="get" style="margin:.4rem 0"><input type="hidden" name="post_type" value="product"><input type="hidden" name="page" value="'.self::PAGE.'">';
        echo '<select name="vk_status">';
        foreach([ 'all' => 'Mind' ] + self::STATUSES as $status => $label) echo '<option value="'.esc_attr($status).'"'.selected($filter, $status, false).'>'.esc_html($label).'</option>';
        echo '</select> <input type="search" name="vk_s" value="'.esc_attr($search).'" placeholder="Keresés névre"> <button class="button">Szűrés</button></form>';

        $options = MigrationClassifier::term_options();
        echo '<p><select class="vk-mig-bulk-term"><option value="">– kategória módosítása –</option>'.$options.'</select> ';
        echo '<button class="button vk-mig-bulk" data-todo="">Kategória beállítása és jóváhagyás</button> <button class="button vk-mig-bulk" data-todo="approve">Jóváhagyás</button> <button class="button vk-mig-bulk" data-todo="claude">Claude-nak újra</button> <button class="button vk-mig-bulk" data-todo="skip">Kihagyás</button> <span class="vk-mig-bulk-msg"></span></p>';

        echo '<table class="widefat striped"><thead><tr><td class="check-column"><input type="checkbox" class="vk-mig-all"></td><th></th><th>Termék</th><th>Régi kategória</th><th>Új kategória</th><th>Forrás</th><th>Állapot</th></tr></thead><tbody>';
        if (!$query->have_posts()) echo '<tr><td colspan="7">Nincs ilyen tétel.</td></tr>';
        foreach($query->posts as $post){
            $data = self::data($post->ID);
            $s = self::suggestion($post->ID);
            $status = self::status($post->ID);
            $term = !empty($s['term']) ? get_term((int)$s['term'], 'product_cat') : null;
            $img = !empty($data['images'][0]) ? '<img src="'.esc_url($data['images'][0]).'" alt="" width="44" height="44" style="object-fit:contain" loading="lazy">' : '';
            $source = ($s['source'] ?? '').(isset($s['confidence']) && ($s['source'] ?? '') === 'claude' ? ' '.round($s['confidence'] * 100).'%' : '');
            echo '<tr><th class="check-column"><input type="checkbox" class="vk-mig-row" value="'.(int)$post->ID.'"></th><td>'.$img.'</td>';
            echo '<td><a href="'.esc_url($data['url'] ?? '#').'" target="_blank" rel="noopener">'.esc_html($post->post_title).'</a><br><small>'.esc_html(($data['sku'] ?? '') ?: '(nincs cikkszám)').' · '.esc_html(number_format_i18n((float)($data['price'] ?? 0))).' Ft'.(!empty($s['note']) ? ' · <em>'.esc_html($s['note']).'</em>' : '').'</small></td>';
            echo '<td><small>'.esc_html(implode(' › ', (array)($data['path'] ?? []))).'</small></td>';
            echo '<td>'.($status === 'imported' ? esc_html($term ? $term->name : '–') : '<select class="vk-mig-term" data-id="'.(int)$post->ID.'"><option value="">–</option>'.str_replace('value="'.(int)($s['term'] ?? 0).'"', 'value="'.(int)($s['term'] ?? 0).'" selected', $options).'</select>').'</td>';
            echo '<td><small>'.esc_html($source).'</small></td><td><span class="vk-badge '.esc_attr($status).'">'.esc_html(self::STATUSES[$status] ?? $status).'</span></td></tr>';
        }
        echo '</tbody></table>';
        if ($query->max_num_pages > 1){
            echo '<p>';
            for ($i = 1; $i <= $query->max_num_pages; $i++){
                echo $i === $paged ? '<strong>'.$i.'</strong> ' : '<a href="'.esc_url(add_query_arg([ 'vk_status' => $filter, 'vk_s' => $search, 'vk_page' => $i ], $base)).'">'.$i.'</a> ';
            }
            echo '</p>';
        }
    }

    /* ---------- WP-CLI: wp vasskisgep migrate <crawl [--reset]|map|ai|import [--refresh]|stock <fájl.csv>|related [--reset]|status> ---------- */

    function cli($args, $assoc = []){
        $what = $args[0] ?? 'status';
        $loops = [ 'related' => function(){ return MigrationRelated::step(); }, 'crawl' => function(){ return MigrationSource::step(); }, 'map' => function(){ return MigrationClassifier::apply_map(400); }, 'ai' => function(){ return MigrationClassifier::ai_step(); }, 'import' => function(){ return MigrationImporter::step(); } ];
        if ($what === 'crawl' && !empty($assoc['reset'])) MigrationSource::reset();
        if ($what === 'related' && !empty($assoc['reset'])) MigrationRelated::reset();
        // Újraimport a friss beolvasásból (a WooCommerce-ben azóta kézzel módosított adatokat felülírja).
        if ($what === 'import' && !empty($assoc['refresh'])){
            foreach(self::ids('imported') as $id) update_post_meta($id, '_vk_mig_status', 'ready');
        }
        if ($what === 'stock'){
            $rows = MigrationStock::parse($args[1] ?? '');
            if (is_wp_error($rows)) \WP_CLI::error($rows->get_error_message());
            \WP_CLI::success(MigrationStock::message(MigrationStock::load($rows)));
            return;
        }
        if (isset($loops[$what])){
            do {
                $result = $loops[$what]();
                if (is_wp_error($result)){
                    $retry = (int)($result->get_error_data()['retry_after'] ?? 0);
                    if (!$retry) \WP_CLI::error($result->get_error_message());
                    \WP_CLI::warning($result->get_error_message().' – újra '.$retry.' mp múlva');
                    sleep($retry);
                    $result = [ 'done' => false, 'message' => '' ];
                }
                if ($result['message'] !== '') \WP_CLI::log($result['message']);
            } while (empty($result['done']));
        }
        foreach(self::counts() as $status => $n) \WP_CLI::log(str_pad(self::STATUSES[$status] ?? $status, 16).$n);
    }
}
