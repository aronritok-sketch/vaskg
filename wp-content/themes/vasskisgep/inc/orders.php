<?php

namespace VasskisgepTheme;

/**
 * Admin rendeléslista: „Termékek” oszlop a rendelt tételekkel (kép, mennyiség, név, cikkszám), hogy a rendelést
 * ne kelljen megnyitni. Sok tételnél kis, görgethető dobozban. HPOS és a régi (bejegyzés alapú) tárolás is.
 *
 * Ügyfélkérés (2026-11, élesítés után): „Státusz” oszlop választóval – a státusz a listából módosítható, több
 * rendelésé egyszerre, egy mentéssel (mint a régi webshopban); nagyobb betű, jobban elváló sorok.
 */
class Orders {
    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'columns']);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'column'], 10, 2);
        add_filter('manage_edit-shop_order_columns', [$this, 'columns']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'column'], 10, 2);
        add_action('admin_head', [$this, 'styles']);
        add_action('admin_footer', [$this, 'status_script']);
        add_action('wp_ajax_vk_orders_status', [$this, 'save_statuses']);
        // A „Feldolgozás alatt” rendelés tételei is szerkeszthetők (tétel hozzáadása, törlése, mennyiség);
        // a WooCommerce alapból csak a függő és a visszatartott rendelést engedi.
        add_filter('wc_order_is_editable', [$this, 'editable'], 10, 2);
    }

    const EDITABLE_STATUSES = [ 'pending', 'on-hold', 'processing', 'auto-draft', 'checkout-draft' ];

    function editable($editable, $order){
        return $editable || ($order instanceof \WC_Order && in_array($order->get_status(), self::EDITABLE_STATUSES, true));
    }

    function columns($columns){
        $out = [];
        foreach($columns as $key => $label){
            if ($key === 'order_status') $label = 'Státusz';
            $out[$key] = $label;
            if ($key === 'order_number') $out['vk_items'] = 'Termékek';
        }
        if (!isset($out['vk_items'])) $out['vk_items'] = 'Termékek';
        return $out;
    }

    function column($column, $order){
        if ($column === 'order_status'){
            $this->status_select($order);
            return;
        }
        if ($column !== 'vk_items') return;
        $order = $order instanceof \WC_Order ? $order : wc_get_order($order);
        if (!$order) return;
        $items = $order->get_items();
        if (!$items){
            echo '<span class="na">–</span>';
            return;
        }
        $count = array_sum(array_map(function($item){ return (int)$item->get_quantity(); }, $items));
        if (count($items) > 1) echo '<div class="vk-order-items-count">'.esc_html(sprintf('%d tétel · %d db', count($items), $count)).'</div>';
        echo '<div class="vk-order-items">';
        foreach($items as $item){
            $product = $item->get_product();
            $image = $product && $product->get_image_id() ? wp_get_attachment_image($product->get_image_id(), [ 32, 32 ], false, [ 'alt' => '' ]) : '<span class="vk-order-item-noimg"></span>';
            $sku = $product ? $product->get_sku() : '';
            echo '<div class="vk-order-item">'.$image
                .'<span class="vk-order-item-text"><b>'.(int)$item->get_quantity().' ×</b> '.esc_html($item->get_name())
                .($sku !== '' ? ' <small>'.esc_html($sku).'</small>' : '').'</span></div>';
        }
        echo '</div>';
    }

    /** Státuszválasztó a listában (a WooCommerce saját jelölése CSS-sel rejtve); a „no-link” miatt a sorra kattintás nem nyitja meg. */
    private function status_select($order){
        $order = $order instanceof \WC_Order ? $order : wc_get_order($order);
        if (!$order || !current_user_can('edit_shop_orders')) return;
        $current = 'wc-'.$order->get_status();
        echo '<span class="no-link vk-status-wrap"><select class="vk-status-select status-'.esc_attr($order->get_status()).'" data-order="'.(int)$order->get_id().'" data-orig="'.esc_attr($current).'" aria-label="Státusz">';
        foreach(wc_get_order_statuses() as $key => $label){
            echo '<option value="'.esc_attr($key).'"'.selected($key, $current, false).'>'.esc_html($label).'</option>';
        }
        echo '</select></span>';
    }

    /** Több rendelés státuszának mentése (a listából). */
    function save_statuses(){
        if (!current_user_can('edit_shop_orders') || !check_ajax_referer('vk_orders_status', 'nonce', false)) wp_send_json_error([ 'message' => 'Nincs jogosultság.' ], 403);
        $statuses = wc_get_order_statuses();
        $changes = (array)json_decode(wp_unslash((string)($_POST['changes'] ?? '')), true);
        $done = 0;
        $errors = [];
        foreach($changes as $id => $status){
            $order = wc_get_order((int)$id);
            if (!$order || !isset($statuses[$status])){ $errors[] = '#'.(int)$id; continue; }
            if ('wc-'.$order->get_status() === $status) continue;
            $order->update_status(substr($status, 3), 'Státusz módosítva a rendeléslistából.', true);
            $done++;
        }
        wp_send_json_success([ 'done' => $done, 'errors' => $errors ]);
    }

    function status_script(){
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->id, [ 'woocommerce_page_wc-orders', 'edit-shop_order' ], true) || !current_user_can('edit_shop_orders')) return;
        ?>
        <div class="vk-status-bar" hidden><span class="vk-status-count"></span><button type="button" class="button button-primary vk-status-save">Státuszok mentése</button><button type="button" class="button vk-status-cancel">Mégse</button></div>
        <script>
        jQuery(function($){
            var $bar = $('.vk-status-bar'), nonce = <?php echo wp_json_encode(wp_create_nonce('vk_orders_status')); ?>;
            function changed(){ return $('.vk-status-select').filter(function(){ return this.value !== $(this).data('orig'); }); }
            function refresh(){
                var n = changed().length;
                $('.vk-status-select').each(function(){ $(this).closest('tr').toggleClass('vk-status-changed', this.value !== $(this).data('orig')); });
                $bar.prop('hidden', n === 0).find('.vk-status-count').text(n + ' rendelés státusza módosult');
            }
            $(document).on('click mousedown', '.vk-status-wrap', function(e){ e.stopPropagation(); });
            $(document).on('change', '.vk-status-select', refresh);
            $bar.on('click', '.vk-status-cancel', function(){ $('.vk-status-select').each(function(){ this.value = $(this).data('orig'); }); refresh(); });
            $bar.on('click', '.vk-status-save', function(){
                var data = {};
                changed().each(function(){ data[$(this).data('order')] = this.value; });
                var $btn = $(this).prop('disabled', true).text('Mentés…');
                $.post(ajaxurl, { action: 'vk_orders_status', nonce: nonce, changes: JSON.stringify(data) }).done(function(r){
                    if (r && r.success){ window.location.reload(); return; }
                    window.alert((r && r.data && r.data.message) || 'A mentés nem sikerült.');
                    $btn.prop('disabled', false).text('Státuszok mentése');
                }).fail(function(){ window.alert('A mentés nem sikerült (kapcsolati hiba).'); $btn.prop('disabled', false).text('Státuszok mentése'); });
            });
            $(window).on('beforeunload', function(){ if (changed().length && !$('.vk-status-save').prop('disabled')) return 'Nem mentett státuszváltozások.'; });
        });
        </script>
        <?php
    }

    function styles(){
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->id, [ 'woocommerce_page_wc-orders', 'edit-shop_order' ], true)) return;
        echo '<style>
            .wp-list-table .column-vk_items { width: 32%; }
            .vk-order-items-count { margin-bottom: .2rem; color: #6b7280; font-size: 12px; }
            .vk-order-items { max-height: 7.6rem; overflow-y: auto; padding-right: .3rem; overscroll-behavior: contain; }
            .vk-order-item { display: flex; align-items: center; gap: .5rem; padding: .2rem 0; line-height: 1.3; }
            .vk-order-item + .vk-order-item { border-top: 1px solid #f0f0f1; }
            .vk-order-item img, .vk-order-item-noimg { flex: 0 0 32px; width: 32px; height: 32px; object-fit: contain; border: 1px solid #e3e5e8; border-radius: 3px; background: #fff; }
            .vk-order-item small { color: #6b7280; }
            /* Nagyobb betű, jobban elváló sorok (ügyfélkérés). */
            .wp-list-table.orders td, .wp-list-table.posts td, .wp-list-table th { font-size: 15px; }
            .wp-list-table.orders tbody td, .wp-list-table.posts tbody td { padding-top: 14px; padding-bottom: 14px; border-bottom: 1px solid #c9ced6; }
            .wp-list-table.orders tbody tr:nth-child(odd) td, .wp-list-table.posts tbody tr:nth-child(odd) td { background: #f6f7f9; }
            .wp-list-table.orders tbody tr:hover td, .wp-list-table.posts tbody tr:hover td { background: #eef4fb; }
            .wp-list-table .column-order_number a strong, .wp-list-table .column-order_number a { font-size: 15px; }
            .vk-order-item { font-size: 14px; }
            /* Státusz a listában: választó (a WooCommerce saját jelölése helyett). */
            .column-order_status mark.order-status { display: none; }
            .column-order_status { width: 13rem; }
            .vk-status-select { width: 100%; max-width: 12.5rem; font-size: 14px; border-radius: 4px; border-left-width: 5px; }
            .vk-status-select.status-processing { border-left-color: #2e7d32; background-color: #eef7ee; }
            .vk-status-select.status-completed { border-left-color: #1565c0; background-color: #eaf2fb; }
            .vk-status-select.status-on-hold { border-left-color: #f0a500; background-color: #fff7e6; }
            .vk-status-select.status-pending { border-left-color: #9e9e9e; }
            .vk-status-select.status-cancelled, .vk-status-select.status-failed, .vk-status-select.status-refunded { border-left-color: #c62828; background-color: #fbeeee; }
            tr.vk-status-changed td { background: #fff3c4 !important; }
            .vk-status-bar { position: fixed; z-index: 9999; left: 50%; bottom: 20px; transform: translateX(-50%); display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: #1d2327; color: #fff; border-radius: 6px; box-shadow: 0 6px 24px rgba(0,0,0,.25); font-size: 14px; }
            .vk-status-bar[hidden] { display: none; }
        </style>';
    }
}
