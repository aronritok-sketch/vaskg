<?php

namespace VasskisgepTheme;

/**
 * Analitika → Gyártók (Dávid, 2026-11-12: „az analitikába gyártók szerint is tudjunk szűrni”): a WooCommerce
 * Analitika adataiból (wc_order_product_lookup + wc_order_stats) gyártónként az eladott darab, a nettó és bruttó
 * bevétel és a rendelések száma a választott időszakban; egy gyártóra kattintva a termékei.
 * Sikertelen, törölt, visszatérített, függő és vázlat rendelések nélkül.
 */
class BrandReport {
    const PAGE = 'vk-brand-report';
    const EXCLUDED = [ 'wc-failed', 'wc-cancelled', 'wc-refunded', 'wc-pending', 'wc-checkout-draft', 'trash', 'auto-draft' ];

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('admin_menu', [$this, 'menu'], 70);
    }

    function menu(){
        // Az Analitika menü alá; ha az nincs, a WooCommerce alá.
        global $submenu;
        $parent = isset($submenu['wc-admin&path=/analytics/overview']) ? 'wc-admin&path=/analytics/overview' : 'woocommerce';
        add_submenu_page($parent, 'Gyártók', 'Gyártók', 'view_woocommerce_reports', self::PAGE, [$this, 'page']);
    }

    private static function range(){
        $preset = sanitize_key($_GET['range'] ?? 'month');
        $tz = wp_timezone();
        $now = new \DateTime('now', $tz);
        $from = (clone $now)->modify('first day of this month')->setTime(0, 0);
        $to = (clone $now)->setTime(23, 59, 59);
        switch($preset){
            case 'today': $from = (clone $now)->setTime(0, 0); break;
            case 'week': $from = (clone $now)->modify('monday this week')->setTime(0, 0); break;
            case 'last_month': $from = (clone $now)->modify('first day of last month')->setTime(0, 0); $to = (clone $now)->modify('last day of last month')->setTime(23, 59, 59); break;
            case 'quarter': $m = (int)$now->format('n'); $from = (clone $now)->setDate((int)$now->format('Y'), $m - (($m - 1) % 3), 1)->setTime(0, 0); break;
            case 'year': $from = (clone $now)->setDate((int)$now->format('Y'), 1, 1)->setTime(0, 0); break;
            case 'last_year': $y = (int)$now->format('Y') - 1; $from = (clone $now)->setDate($y, 1, 1)->setTime(0, 0); $to = (clone $now)->setDate($y, 12, 31)->setTime(23, 59, 59); break;
            case 'custom':
                $f = sanitize_text_field(wp_unslash($_GET['from'] ?? '')); $t = sanitize_text_field(wp_unslash($_GET['to'] ?? ''));
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) $from = new \DateTime($f.' 00:00:00', $tz);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $t)) $to = new \DateTime($t.' 23:59:59', $tz);
                break;
            default: $preset = 'month';
        }
        return [ $preset, $from, $to ];
    }

    /** Gyártónként (vagy egy gyártó termékenként) összesítve. */
    static function data($from, $to, $brand = 0){
        global $wpdb;
        $lookup = $wpdb->prefix.'wc_order_product_lookup';
        $stats = $wpdb->prefix.'wc_order_stats';
        $excluded = "'".implode("','", array_map('esc_sql', self::EXCLUDED))."'";
        $where = $wpdb->prepare("s.date_created >= %s AND s.date_created <= %s AND s.status NOT IN ($excluded)", $from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s'));
        // Termékenként egy gyártó (a márka taxonómia), így a SUM nem duplázódik.
        $brands = "SELECT tr.object_id, MIN(tt.term_id) brand FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_brand' GROUP BY tr.object_id";
        if ($brand){
            return $wpdb->get_results($wpdb->prepare("SELECT l.product_id id, SUM(l.product_qty) qty, SUM(l.product_net_revenue) net, SUM(l.product_gross_revenue) gross, COUNT(DISTINCT l.order_id) orders
                FROM $lookup l JOIN $stats s ON s.order_id = l.order_id JOIN ($brands) b ON b.object_id = l.product_id
                WHERE $where AND b.brand = %d GROUP BY l.product_id ORDER BY gross DESC", $brand));
        }
        return $wpdb->get_results("SELECT COALESCE(b.brand, 0) brand, SUM(l.product_qty) qty, SUM(l.product_net_revenue) net, SUM(l.product_gross_revenue) gross, COUNT(DISTINCT l.order_id) orders, COUNT(DISTINCT l.product_id) products
            FROM $lookup l JOIN $stats s ON s.order_id = l.order_id LEFT JOIN ($brands) b ON b.object_id = l.product_id
            WHERE $where GROUP BY COALESCE(b.brand, 0) ORDER BY gross DESC");
    }

    function page(){
        if (!current_user_can('view_woocommerce_reports')) wp_die('Nincs jogosultság.');
        [$preset, $from, $to] = self::range();
        $brand = (int)($_GET['brand'] ?? 0);
        $base = admin_url('admin.php?page='.self::PAGE);
        $money = function($v){ return number_format((float)$v, 0, ',', ' ').' Ft'; };
        echo '<div class="wrap"><h1>Forgalom gyártónként</h1>';
        $presets = [ 'today' => 'Ma', 'week' => 'Ez a hét', 'month' => 'Ez a hónap', 'last_month' => 'Múlt hónap', 'quarter' => 'Ez a negyedév', 'year' => 'Ez az év', 'last_year' => 'Múlt év' ];
        echo '<form method="get" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:12px 0"><input type="hidden" name="page" value="'.esc_attr(self::PAGE).'">';
        if ($brand) echo '<input type="hidden" name="brand" value="'.(int)$brand.'">';
        echo '<select name="range">';
        foreach($presets as $k => $label) echo '<option value="'.esc_attr($k).'"'.selected($preset, $k, false).'>'.esc_html($label).'</option>';
        echo '<option value="custom"'.selected($preset, 'custom', false).'>Egyéni időszak</option></select>';
        echo '<input type="date" name="from" value="'.esc_attr($from->format('Y-m-d')).'"> – <input type="date" name="to" value="'.esc_attr($to->format('Y-m-d')).'">';
        echo '<button class="button button-primary">Mutasd</button><span class="description">Egyéni dátumhoz válaszd az „Egyéni időszak”-ot.</span></form>';
        echo '<p class="description">Időszak: '.esc_html(wp_date('Y. m. d.', $from->getTimestamp()).' – '.wp_date('Y. m. d.', $to->getTimestamp())).'. A sikertelen, törölt, visszatérített és fizetésre váró rendelések nélkül; az Analitika adataiból.</p>';

        if ($brand){
            $term = get_term($brand, 'product_brand');
            $rows = self::data($from, $to, $brand);
            echo '<p><a href="'.esc_url(add_query_arg([ 'range' => $preset, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d') ], $base)).'">← Összes gyártó</a></p>';
            echo '<h2>'.esc_html($term && !is_wp_error($term) ? $term->name : 'Gyártó').' – termékenként</h2>';
            echo '<table class="widefat striped"><thead><tr><th>Termék</th><th style="text-align:right">Eladott db</th><th style="text-align:right">Rendelések</th><th style="text-align:right">Nettó bevétel</th><th style="text-align:right">Bruttó bevétel</th></tr></thead><tbody>';
            $sum = [ 0, 0, 0 ];
            foreach((array)$rows as $r){
                $sum[0] += $r->qty; $sum[1] += $r->net; $sum[2] += $r->gross;
                echo '<tr><td><a href="'.esc_url(get_edit_post_link((int)$r->id, 'raw')).'">'.esc_html(get_the_title((int)$r->id)).'</a></td><td style="text-align:right">'.(int)$r->qty.'</td><td style="text-align:right">'.(int)$r->orders.'</td><td style="text-align:right">'.$money($r->net).'</td><td style="text-align:right">'.$money($r->gross).'</td></tr>';
            }
            if (!$rows) echo '<tr><td colspan="5">Ebben az időszakban nincs eladás.</td></tr>';
            echo '</tbody><tfoot><tr><th>Összesen</th><th style="text-align:right">'.(int)$sum[0].'</th><th></th><th style="text-align:right">'.$money($sum[1]).'</th><th style="text-align:right">'.$money($sum[2]).'</th></tr></tfoot></table></div>';
            return;
        }

        $rows = self::data($from, $to);
        $total = array_sum(array_map(function($r){ return (float)$r->gross; }, (array)$rows));
        echo '<table class="widefat striped"><thead><tr><th>Gyártó</th><th style="text-align:right">Eladott db</th><th style="text-align:right">Termékek</th><th style="text-align:right">Rendelések</th><th style="text-align:right">Nettó bevétel</th><th style="text-align:right">Bruttó bevétel</th><th style="text-align:right">Arány</th></tr></thead><tbody>';
        foreach((array)$rows as $r){
            $term = $r->brand ? get_term((int)$r->brand, 'product_brand') : null;
            $name = $term && !is_wp_error($term) ? $term->name : 'Gyártó nélkül';
            $link = $r->brand ? '<a href="'.esc_url(add_query_arg([ 'brand' => (int)$r->brand, 'range' => $preset, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d') ], $base)).'"><strong>'.esc_html($name).'</strong></a>' : esc_html($name);
            echo '<tr><td>'.$link.'</td><td style="text-align:right">'.(int)$r->qty.'</td><td style="text-align:right">'.(int)$r->products.'</td><td style="text-align:right">'.(int)$r->orders.'</td><td style="text-align:right">'.$money($r->net).'</td><td style="text-align:right">'.$money($r->gross).'</td><td style="text-align:right">'.($total > 0 ? number_format(100 * $r->gross / $total, 1, ',', '').'%' : '–').'</td></tr>';
        }
        if (!$rows) echo '<tr><td colspan="7">Ebben az időszakban nincs eladás.</td></tr>';
        echo '</tbody><tfoot><tr><th>Összesen</th><th colspan="4"></th><th style="text-align:right">'.$money($total).'</th><th></th></tr></tfoot></table>';
        echo '<p class="description">Egy gyártó nevére kattintva a termékei látszanak.</p></div>';
    }
}
