<?php

namespace VasskisgepTheme;

/**
 * Ár-import Excelből (Dávid, 2026-11-12: a STIHL kampány- / árváltozás-listáiból egyszerre). Termékek → Ár-import (Excel).
 *
 * 1. Fájl (.xlsx vagy .csv) feltöltése → 2. előnézet: melyik oszlop a cikkszám, az akciós ár és (ha kell) a rendes ár
 * (a fejléc alapján előre kiválasztva: „Cikkszám”, „…kampányár / akciós…”), soronként a jelenlegi és az új ár, a nem
 * talált cikkszámok → 3. alkalmazás. Akciónál kezdő- és záró dátum adható (a WooCommerce a végén magától leveszi).
 * „Akció törlése” mód: a listában szereplő termékekről leveszi az akciós árat (kampány vége).
 * A cikkszám a termék vagy változat „Cikkszám” (SKU) mezője; szóköz / kötőjel nélkül is egyezik.
 *
 * Saját ár (zárolás, Dávid, 2026-11-13): a terméknél (Általános fül, az áraknál) vagy a terméklistában tömegesen
 * bekapcsolható, és az import is bekapcsolhatja az átírt termékeken. A zárolt termék árát a gyártói / automatikus
 * árfrissítés nem írja át, amíg ki nem kapcsolják (`PriceImport::locked()` – a későbbi gyártói API ezt nézi).
 */
class PriceImport {
    const PAGE = 'vk-price-import';
    const LOCK = '_vk_price_lock';

    /** Saját ár: az automatikus (gyártói) árfrissítés nem írja át. Változatnál a szülő zárolása is számít. */
    static function locked($product_id){
        $product_id = (int)$product_id;
        if (get_post_meta($product_id, self::LOCK, true) === 'yes') return true;
        $parent = (int)wp_get_post_parent_id($product_id);
        return $parent && get_post_meta($parent, self::LOCK, true) === 'yes';
    }

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('admin_menu', [$this, 'menu'], 60);
        add_action('woocommerce_product_options_pricing', [$this, 'lock_field'], 30);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_lock']);
        add_filter('bulk_actions-edit-product', [$this, 'bulk_actions'], 40);
        add_filter('handle_bulk_actions-edit-product', [$this, 'handle_bulk'], 10, 3);
        add_action('manage_product_posts_custom_column', [$this, 'price_column_mark'], 20, 2);
        add_action('admin_notices', [$this, 'bulk_notice']);
    }

    function lock_field(){
        global $post;
        woocommerce_wp_checkbox([
            'id' => self::LOCK,
            'value' => get_post_meta($post->ID, self::LOCK, true) === 'yes' ? 'yes' : 'no',
            'label' => 'Saját ár (zárolva)',
            'description' => 'Az automatikus / gyártói árfrissítés nem írja át ennek a terméknek az árát, amíg ki nem veszed a pipát.',
        ]);
    }

    function save_lock($product){
        $product->update_meta_data(self::LOCK, !empty($_POST[self::LOCK]) ? 'yes' : 'no');
    }

    function bulk_actions($actions){
        $actions['vk_lock_on'] = 'Saját ár: zárolás be (az automatikus árfrissítés nem írja át)';
        $actions['vk_lock_off'] = 'Saját ár: zárolás ki (az automatikus árfrissítés frissítheti)';
        return $actions;
    }

    function handle_bulk($redirect, $action, $ids){
        if (!in_array($action, [ 'vk_lock_on', 'vk_lock_off' ], true) || !current_user_can('edit_products')) return $redirect;
        foreach((array)$ids as $id) update_post_meta((int)$id, self::LOCK, $action === 'vk_lock_on' ? 'yes' : 'no');
        return add_query_arg([ 'vk_lock_done' => count((array)$ids), 'vk_lock_on' => $action === 'vk_lock_on' ? 1 : 0 ], $redirect);
    }

    function bulk_notice(){
        if (!isset($_GET['vk_lock_done'])) return;
        echo '<div class="notice notice-success is-dismissible"><p>'.(int)$_GET['vk_lock_done'].' terméken a saját ár zárolása '.(!empty($_GET['vk_lock_on']) ? 'bekapcsolva' : 'kikapcsolva').'.</p></div>';
    }

    function price_column_mark($column, $post_id){
        if ($column === 'price' && get_post_meta((int)$post_id, self::LOCK, true) === 'yes') echo '<br><span class="description" title="Az automatikus árfrissítés nem írja át">🔒 saját ár</span>';
    }

    function menu(){
        add_submenu_page('edit.php?post_type=product', 'Ár-import (Excel)', 'Ár-import (Excel)', 'edit_products', self::PAGE, [$this, 'page']);
    }

    private static function transient_key(){
        return 'vk_price_import_'.get_current_user_id();
    }

    /* ---------- Fájl beolvasása ---------- */

    /** Sorok (első sor a fejléc) egy .xlsx vagy .csv fájlból, vagy WP_Error. */
    static function read_file($path, $name){
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === 'csv') return self::read_csv($path);
        if ($ext === 'xlsx') return self::read_xlsx($path);
        return new \WP_Error('type', 'Csak .xlsx (Excel) vagy .csv fájl tölthető fel. Régi .xls fájlt az Excelben mentsd el .xlsx-ként.');
    }

    private static function read_csv($path){
        $raw = (string)file_get_contents($path);
        if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1250');
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $first = (string)($lines[0] ?? '');
        $sep = substr_count($first, ';') >= substr_count($first, ',') ? (substr_count($first, "\t") > substr_count($first, ';') ? "\t" : ';') : ',';
        $rows = [];
        foreach($lines as $line){
            if (trim($line) === '') continue;
            $rows[] = array_map('trim', str_getcsv($line, $sep));
        }
        return $rows;
    }

    private static function read_xlsx($path){
        if (!class_exists('\ZipArchive')) return new \WP_Error('zip', 'A tárhelyen nincs ZipArchive: mentsd el a táblázatot .csv formátumban, és azt töltsd fel.');
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) return new \WP_Error('zip', 'A fájl nem olvasható Excel (.xlsx) fájlként.');
        $shared = [];
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml !== false){
            $sx = @simplexml_load_string($xml);
            if ($sx) foreach($sx->si as $si){
                if (isset($si->t)) $shared[] = (string)$si->t;
                else { $t = ''; foreach($si->r as $r) $t .= (string)$r->t; $shared[] = $t; }
            }
        }
        // Az első munkalap (a workbook sorrendje szerint).
        $sheet = 'xl/worksheets/sheet1.xml';
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb !== false && $rels !== false && preg_match('/<sheet[^>]*r:id="([^"]+)"/', $wb, $m) && preg_match('/<Relationship[^>]*Id="'.preg_quote($m[1], '/').'"[^>]*Target="([^"]+)"/', $rels, $t)){
            $target = ltrim($t[1], '/');
            $sheet = strpos($target, 'xl/') === 0 ? $target : 'xl/'.$target;
        }
        $xml = $zip->getFromName($sheet);
        $zip->close();
        if ($xml === false) return new \WP_Error('sheet', 'Nem találom az első munkalapot.');
        $sx = @simplexml_load_string($xml);
        if (!$sx) return new \WP_Error('sheet', 'A munkalap nem olvasható.');
        $rows = [];
        foreach($sx->sheetData->row as $row){
            $cells = [];
            foreach($row->c as $c){
                $ref = (string)$c['r'];
                $col = preg_replace('/\d+/', '', $ref);
                $idx = 0;
                foreach(str_split($col) as $ch) $idx = $idx * 26 + (ord($ch) - 64);
                $type = (string)$c['t'];
                if ($type === 's') $value = $shared[(int)$c->v] ?? '';
                elseif ($type === 'inlineStr') $value = (string)$c->is->t;
                else $value = (string)$c->v;
                $cells[$idx - 1] = trim($value);
            }
            if (!$cells) continue;
            $max = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $max; $i++) $line[] = $cells[$i] ?? '';
            if (implode('', $line) === '') continue;
            $rows[] = $line;
        }
        return $rows;
    }

    /* ---------- Egyeztetés ---------- */

    private static function norm_sku($sku){
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$sku));
    }

    /** Cikkszám (normalizált) → termék / változat azonosító. */
    private static function sku_map(){
        global $wpdb;
        $map = [];
        foreach($wpdb->get_results("SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type IN ('product','product_variation') AND p.post_status NOT IN ('trash','auto-draft') WHERE pm.meta_key = '_sku' AND pm.meta_value <> ''") as $r){
            $key = self::norm_sku($r->meta_value);
            if ($key !== '' && !isset($map[$key])) $map[$key] = (int)$r->post_id;
        }
        return $map;
    }

    private static function number($v){
        $v = str_replace([ ' ', "\xC2\xA0", 'Ft', 'HUF' ], '', (string)$v);
        if ($v === '') return null;
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $v)) $v = str_replace([ '.', ',' ], [ '', '.' ], $v);
        else $v = str_replace(',', '.', $v);
        return is_numeric($v) ? (float)$v : null;
    }

    /** A fejléc alapján javasolt oszlopok. */
    private static function guess(array $head){
        $find = function($patterns) use ($head){
            foreach($patterns as $re) foreach($head as $i => $h) if (preg_match($re, mb_strtolower($h))) return $i;
            return -1;
        };
        return [
            'sku' => $find([ '/cikksz/u', '/^sku$/u', '/termékszám|termekszam|azonosító/u' ]),
            'sale' => $find([ '/kampány.*ár|kampanyar/u', '/akció.*ár|akcios/u', '/sale/u' ]),
            'regular' => -1,
            // Súly és dobozméret (Dávid, 2026-11-26: a STIHL táblázatában ugyanitt van).
            'weight' => $find([ '/súly|suly|tömeg|tomeg|weight/u' ]),
            'length' => $find([ '/hossz|length/u' ]),
            'width' => $find([ '/szélesség|szelesseg|width/u' ]),
            'height' => $find([ '/magasság|magassag|height/u' ]),
            'dims' => $find([ '/méret|meret|dimension|csomagolás/u' ]),
        ];
    }

    /** Súly kg-ban, méretek cm-ben a választott mértékegységből; a „Méret” oszlop „30x20x10” alakú is lehet. */
    private static function measures(array $line, array $cols){
        $w = $cols['weight'] >= 0 ? self::number($line[$cols['weight']] ?? '') : null;
        if ($w !== null && $w > 0 && ($cols['wunit'] ?? 'kg') === 'g') $w = $w / 1000;
        $d = [];
        foreach([ 'length', 'width', 'height' ] as $k) $d[$k] = $cols[$k] >= 0 ? self::number($line[$cols[$k]] ?? '') : null;
        if ($cols['dims'] >= 0 && $d['length'] === null && preg_match_all('/\d+(?:[.,]\d+)?/', (string)($line[$cols['dims']] ?? ''), $m) && count($m[0]) >= 3){
            [$d['length'], $d['width'], $d['height']] = array_map(function($v){ return (float)str_replace(',', '.', $v); }, array_slice($m[0], 0, 3));
        }
        foreach($d as $k => $v){
            if ($v === null || $v <= 0){ $d[$k] = null; continue; }
            if (($cols['dunit'] ?? 'cm') === 'mm') $v = $v / 10;
            $d[$k] = round($v, 1);
        }
        return [ 'weight' => $w !== null && $w > 0 ? round($w, 3) : null ] + $d;
    }

    /** Az előnézet sorai a választott oszlopokkal és móddal. */
    private static function plan(array $rows, array $cols, $mode){
        $map = self::sku_map();
        $out = [];
        $seen = [];
        foreach(array_slice($rows, 1) as $line){
            $sku = trim((string)($line[$cols['sku']] ?? ''));
            if ($sku === '') continue;
            $key = self::norm_sku($sku);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $item = [ 'sku' => $sku, 'name' => (string)($line[1] ?? ''), 'id' => (int)($map[$key] ?? 0), 'sale' => null, 'regular' => null, 'note' => '', 'ok' => false ];
            if ($cols['sale'] >= 0) { $n = self::number($line[$cols['sale']] ?? ''); $item['sale'] = $n !== null && $n > 0 ? round($n) : null; }
            if ($cols['regular'] >= 0) { $n = self::number($line[$cols['regular']] ?? ''); $item['regular'] = $n !== null && $n > 0 ? round($n) : null; }
            $item += self::measures($line, $cols);
            if (!$item['id']){ $item['note'] = 'Nincs ilyen cikkszámú termék a webshopban.'; $out[] = $item; continue; }
            $product = wc_get_product($item['id']);
            if (!$product){ $item['note'] = 'A termék nem olvasható.'; $out[] = $item; continue; }
            $item['product'] = $product->get_name();
            $item['link'] = get_edit_post_link($product->get_parent_id() ?: $product->get_id(), 'raw');
            $item['cur_regular'] = $product->get_regular_price();
            $item['cur_sale'] = $product->get_sale_price();
            $item['locked'] = self::locked($product->get_id());
            $regular = $item['regular'] !== null ? $item['regular'] : (float)$item['cur_regular'];
            if ($mode === 'clear'){
                $item['ok'] = $item['cur_sale'] !== '';
                $item['note'] = $item['ok'] ? 'Az akciós ár törlődik.' : 'Nincs rajta akció.';
            } elseif ($mode === 'sale'){
                $price_ok = false;
                if ($item['sale'] === null && $item['regular'] === null){ $item['note'] = 'Ebben a sorban nincs ár.'; }
                elseif ($item['sale'] !== null && $regular > 0 && $item['sale'] >= $regular){ $item['note'] = 'Az akciós ár nem kisebb a rendes árnál – az ár kimarad.'; $item['sale'] = null; }
                elseif (($item['sale'] === null || (string)$item['sale'] === (string)$item['cur_sale']) && ($item['regular'] === null || (string)$item['regular'] === (string)$item['cur_regular'])){ $item['note'] = 'Az ár nem változik.'; }
                else $price_ok = true;
                // Súly és méret: csak ha van érték és eltér.
                $cur = [ 'weight' => (float)$product->get_weight(), 'length' => (float)$product->get_length(), 'width' => (float)$product->get_width(), 'height' => (float)$product->get_height() ];
                $item['measure_ok'] = false;
                foreach($cur as $k => $v) if ($item[$k] !== null && abs((float)$item[$k] - $v) > 0.0001) $item['measure_ok'] = true;
                if (!$price_ok && !empty($item['sale_blocked'])) $item['sale'] = null;
                if (!$price_ok){ $item['regular'] = null; $item['sale'] = null; }
                $item['ok'] = $price_ok || $item['measure_ok'];
                if ($item['measure_ok']) $item['note'] = trim(($price_ok ? '' : $item['note'].' ').'Súly / méret frissül.');
            }
            $out[] = $item;
        }
        return $out;
    }

    private static function apply(array $plan, $mode, $from, $to, $lock = false){
        $done = 0;
        foreach($plan as $item){
            if (empty($item['ok'])) continue;
            $product = wc_get_product($item['id']);
            if (!$product) continue;
            if ($mode === 'clear'){
                $product->set_sale_price('');
                $product->set_date_on_sale_from(null);
                $product->set_date_on_sale_to(null);
            } else {
                if (!empty($item['measure_ok'])){
                    if ($item['weight'] !== null) $product->set_weight((string)$item['weight']);
                    foreach([ 'length', 'width', 'height' ] as $k) if ($item[$k] !== null) $product->{'set_'.$k}((string)$item[$k]);
                }
                if ($item['regular'] !== null) $product->set_regular_price((string)$item['regular']);
                if ($item['sale'] !== null){
                    $product->set_sale_price((string)$item['sale']);
                    $product->set_date_on_sale_from($from !== '' ? $from.' 00:00:00' : null);
                    $product->set_date_on_sale_to($to !== '' ? $to.' 23:59:59' : null);
                }
            }
            if ($lock) $product->update_meta_data(self::LOCK, 'yes');
            $product->save();
            $done++;
        }
        if ($done && class_exists(__NAMESPACE__.'\\Feed')) Feed::flush();
        return $done;
    }

    /* ---------- Oldal ---------- */

    function page(){
        if (!current_user_can('edit_products')) wp_die('Nincs jogosultság.');
        echo '<div class="wrap"><h1>Ár-import (Excel)</h1>';
        $key = self::transient_key();
        $step = sanitize_key($_POST['vk_step'] ?? '');
        if ($step !== '' && !check_admin_referer('vk_price_import')) return;

        if ($step === 'upload'){
            if (empty($_FILES['vk_file']['tmp_name'])) { echo '<div class="notice notice-error"><p>Nem választottál fájlt.</p></div>'; }
            else {
                $rows = self::read_file($_FILES['vk_file']['tmp_name'], (string)$_FILES['vk_file']['name']);
                if (is_wp_error($rows)) echo '<div class="notice notice-error"><p>'.esc_html($rows->get_error_message()).'</p></div>';
                elseif (count($rows) < 2) echo '<div class="notice notice-error"><p>A táblázatban nincs adat (az első sor a fejléc legyen).</p></div>';
                else {
                    set_transient($key, [ 'rows' => $rows, 'file' => sanitize_file_name((string)$_FILES['vk_file']['name']) ], 2 * HOUR_IN_SECONDS);
                    $step = 'preview';
                }
            }
        }
        $data = get_transient($key);
        if (in_array($step, [ 'preview', 'apply' ], true) && is_array($data)){
            $rows = $data['rows'];
            $head = $rows[0];
            $guess = self::guess($head);
            $cols = [
                'sku' => isset($_POST['vk_col_sku']) ? (int)$_POST['vk_col_sku'] : $guess['sku'],
                'sale' => isset($_POST['vk_col_sale']) ? (int)$_POST['vk_col_sale'] : $guess['sale'],
                'regular' => isset($_POST['vk_col_regular']) ? (int)$_POST['vk_col_regular'] : $guess['regular'],
                'weight' => isset($_POST['vk_col_weight']) ? (int)$_POST['vk_col_weight'] : $guess['weight'],
                'length' => isset($_POST['vk_col_length']) ? (int)$_POST['vk_col_length'] : $guess['length'],
                'width' => isset($_POST['vk_col_width']) ? (int)$_POST['vk_col_width'] : $guess['width'],
                'height' => isset($_POST['vk_col_height']) ? (int)$_POST['vk_col_height'] : $guess['height'],
                'dims' => isset($_POST['vk_col_dims']) ? (int)$_POST['vk_col_dims'] : $guess['dims'],
                'wunit' => ($_POST['vk_wunit'] ?? 'kg') === 'g' ? 'g' : 'kg',
                'dunit' => ($_POST['vk_dunit'] ?? 'cm') === 'mm' ? 'mm' : 'cm',
            ];
            $mode = sanitize_key(wp_unslash($_POST['vk_mode'] ?? 'sale'));
            if (!in_array($mode, [ 'sale', 'clear' ], true)) $mode = 'sale';
            $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['vk_from'] ?? '')) ? (string)$_POST['vk_from'] : '';
            $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['vk_to'] ?? '')) ? (string)$_POST['vk_to'] : '';
            if ($cols['sku'] < 0){
                echo '<div class="notice notice-error"><p>Válaszd ki, melyik oszlopban van a cikkszám.</p></div>';
            }
            $plan = $cols['sku'] >= 0 ? self::plan($rows, $cols, $mode) : [];
            if ($step === 'apply' && $plan){
                $lock = !empty($_POST['vk_lock']) && $mode === 'sale';
                $done = self::apply($plan, $mode, $from, $to, $lock);
                delete_transient($key);
                echo '<div class="notice notice-success"><p><strong>Kész: '.(int)$done.' termék frissült.</strong> '.($mode === 'clear' ? '(akciós ár törölve)' : '').($lock ? ' A saját ár zárolása bekapcsolva rajtuk.' : '').'</p></div>';
                echo '<p><a class="button" href="'.esc_url(admin_url('edit.php?post_type=product&page='.self::PAGE)).'">Új fájl feltöltése</a></p></div>';
                return;
            }
            $ok = count(array_filter($plan, function($p){ return !empty($p['ok']); }));
            $missing = count(array_filter($plan, function($p){ return !$p['id']; }));
            echo '<form method="post">';
            wp_nonce_field('vk_price_import');
            echo '<p><strong>Fájl:</strong> '.esc_html($data['file']).' – '.(count($rows) - 1).' sor.</p>';
            $select = function($name, $value, $none) use ($head){
                $h = '<select name="'.esc_attr($name).'">'.($none ? '<option value="-1">– nincs –</option>' : '');
                foreach($head as $i => $label) $h .= '<option value="'.(int)$i.'"'.selected($value, $i, false).'>'.esc_html(chr(65 + $i % 26).': '.$label).'</option>';
                return $h.'</select>';
            };
            echo '<table class="form-table"><tr><th>Mit csináljon?</th><td><label><input type="radio" name="vk_mode" value="sale"'.checked($mode, 'sale', false).'> Akciós ár beállítása (és ha kiválasztod, rendes ár, súly, dobozméret)</label><br><label><input type="radio" name="vk_mode" value="clear"'.checked($mode, 'clear', false).'> Akció törlése a listában szereplő termékekről (kampány vége)</label></td></tr>';
            echo '<tr><th>Cikkszám oszlop</th><td>'.$select('vk_col_sku', $cols['sku'], false).'</td></tr>';
            echo '<tr><th>Akciós ár oszlop</th><td>'.$select('vk_col_sale', $cols['sale'], true).' <span class="description">bruttó, Ft (pl. „Javasolt végfelhasználói bruttó kampányár”)</span></td></tr>';
            echo '<tr><th>Rendes ár oszlop</th><td>'.$select('vk_col_regular', $cols['regular'], true).' <span class="description">csak ha a rendes (lista) árat is frissíteni akarod – bruttó végfelhasználói ár, NEM a partner ár</span></td></tr>';
            $unit = function($name, $value, $options){ $h = '<select name="'.esc_attr($name).'">'; foreach($options as $o) $h .= '<option value="'.esc_attr($o).'"'.selected($value, $o, false).'>'.esc_html($o).'</option>'; return $h.'</select>'; };
            echo '<tr><th>Súly oszlop</th><td>'.$select('vk_col_weight', $cols['weight'], true).' '.$unit('vk_wunit', $cols['wunit'], [ 'kg', 'g' ]).' <span class="description">nem kötelező; csak ha a táblázatban van</span></td></tr>';
            echo '<tr><th>Dobozméret oszlopok</th><td>Hossz '.$select('vk_col_length', $cols['length'], true).' Szélesség '.$select('vk_col_width', $cols['width'], true).' Magasság '.$select('vk_col_height', $cols['height'], true).' '.$unit('vk_dunit', $cols['dunit'], [ 'cm', 'mm' ]).'<br>vagy egy oszlopban („30x20x10”): '.$select('vk_col_dims', $cols['dims'], true).' <span class="description">nem kötelező</span></td></tr>';
            echo '<tr><th>Akció időszaka</th><td><input type="date" name="vk_from" value="'.esc_attr($from).'"> – <input type="date" name="vk_to" value="'.esc_attr($to).'"> <span class="description">nem kötelező; a záró nap után a WooCommerce magától visszaállítja a rendes árat</span></td></tr>';
            echo '<tr><th>Saját ár</th><td><label><input type="checkbox" name="vk_lock" value="1"'.checked(!empty($_POST['vk_lock']), true, false).'> Az átírt termékeken kapcsold be a saját ár zárolását</label><br><span class="description">pl. ha a gyártói ár alá viszitek: így az automatikus / gyártói árfrissítés nem írja vissza, amíg a terméknél ki nem kapcsoljátok</span></td></tr></table>';
            echo '<p><button class="button" name="vk_step" value="preview">Előnézet frissítése</button></p>';
            echo '<h2>Előnézet</h2><p><strong>'.(int)$ok.'</strong> termék változik, '.(int)$missing.' cikkszám nincs a webshopban.</p>';
            echo '<table class="widefat striped"><thead><tr><th>Cikkszám</th><th>Termék</th><th>Jelenlegi rendes ár</th><th>Jelenlegi akciós ár</th><th>Új rendes ár</th><th>Új akciós ár</th><th>Új súly / méret</th><th>Megjegyzés</th></tr></thead><tbody>';
            usort($plan, function($a, $b){ return [ empty($a['ok']), !$a['id'] ] <=> [ empty($b['ok']), !$b['id'] ]; });
            foreach($plan as $p){
                $fmt = function($v){ return $v === null || $v === '' ? '–' : number_format((float)$v, 0, ',', ' ').' Ft'; };
                echo '<tr'.(empty($p['ok']) ? ' style="opacity:.6"' : '').'><td>'.esc_html($p['sku']).'</td><td>'.($p['id'] ? '<a href="'.esc_url($p['link']).'" target="_blank">'.esc_html($p['product']).'</a>' : esc_html($p['name'])).'</td>'
                    .'<td>'.$fmt($p['cur_regular'] ?? null).'</td><td>'.$fmt($p['cur_sale'] ?? null).'</td>'
                    .'<td>'.($mode === 'clear' ? '–' : $fmt($p['regular'])).'</td><td>'.($mode === 'clear' ? 'törlés' : $fmt($p['sale'])).'</td>'
                    .'<td>'.(!empty($p['measure_ok']) ? esc_html(trim(($p['weight'] !== null ? str_replace('.', ',', (string)$p['weight']).' kg ' : '').(($p['length'] ?? null) !== null ? $p['length'].'×'.$p['width'].'×'.$p['height'].' cm' : ''))) : '–').'</td><td>'.esc_html($p['note']).(!empty($p['locked']) ? ' 🔒 saját ár' : '').'</td></tr>';
            }
            echo '</tbody></table>';
            if ($ok) echo '<p><button class="button button-primary" name="vk_step" value="apply" onclick="return confirm(\'Biztosan frissíted '.(int)$ok.' termék árát?\')">Árak frissítése ('.(int)$ok.' termék)</button></p>';
            echo '</form></div>';
            return;
        }

        echo '<p>Tölts fel egy Excel (.xlsx) vagy CSV táblázatot (pl. a STIHL kampány-listáját). Az első sor a fejléc legyen. A következő lépésben megnézheted, mi változik, és csak utána frissülnek az árak.</p>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('vk_price_import');
        echo '<p><input type="file" name="vk_file" accept=".xlsx,.csv" required></p>';
        echo '<p><button class="button button-primary" name="vk_step" value="upload">Feltöltés és előnézet</button></p></form></div>';
    }
}
