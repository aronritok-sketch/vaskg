<?php

namespace VasskisgepTheme;

/**
 * Kosáremlékeztető levél (AbandonedCart): a kosárban hagyott termékek, „Vissza a kosárhoz” gomb, „Miért minket”
 * sáv, „Ez is érdekelhet” ajánlatok, kategóriák, leiratkozás – a küldött minta felépítése szerint, a webshop
 * arculatával. Táblázatos, beágyazott stílusú HTML (a levelezők miatt).
 */
class VK_Email_Cart extends \WC_Email {
    public $row = null;

    function __construct(){
        $this->id = 'vk_cart_reminder';
        $this->customer_email = true;
        $this->title = 'Kosáremlékeztető (vásárlónak)';
        $this->description = 'Aznap 20:00-kor megy ki annak, aki termékeket hagyott a kosarában és nem rendelt (ha tudjuk az e-mail címét). Ugyanarról a kosárról csak egyszer.';
        parent::__construct();
    }

    function init_form_fields(){
        parent::init_form_fields();
        $url = wp_nonce_url(admin_url('admin-post.php?action=vk_cart_test'), 'vk_cart_test');
        $msg = isset($_GET['vk_cart_test']) ? ($_GET['vk_cart_test'] === 'ok' ? ' <strong style="color:#2e7d32">Elküldve.</strong>' : ' <strong style="color:#b32d2e">Nem ment ki (be van kapcsolva a levél?).</strong>') : '';
        $this->form_fields = [ 'vk_test' => [
            'title' => 'Próbalevél',
            'type' => 'title',
            'description' => '<a class="button" href="'.esc_url($url).'">Próbalevél nekem</a> – a levél a te e-mail címedre megy, néhány mostani termékkel.'.$msg
                .'<br>Élesben a levél <strong>naponta 20:00-kor</strong> megy ki annak, aki az előző 24 órában (legalább fél órája) termékeket hagyott a kosarában és nem rendelt – ha tudjuk az e-mail címét (belépett vásárló, vagy a pénztárban beírta).',
        ] ] + $this->form_fields;
    }

    function get_default_subject(){ return 'Kosaradban maradt néhány termék – Vass Kisgép'; }
    function get_default_heading(){ return 'Nézd át a kosaradban hagyott termékeket!'; }

    /** @return bool elküldve */
    function trigger($row){
        if (!$row || !is_email($row->email)) return false;
        $this->setup_locale();
        $this->row = $row;
        $this->recipient = $row->email;
        $ok = false;
        if ($this->is_enabled() && $this->items()){
            $ok = (bool)$this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
        $this->restore_locale();
        return $ok;
    }

    /** [ [ WC_Product, qty ], … ] a még megvásárolható termékekből. */
    private function items(){
        $out = [];
        foreach((array)json_decode((string)($this->row->items ?? '[]'), true) as $item){
            $product = wc_get_product(!empty($item['variation_id']) ? $item['variation_id'] : $item['product_id']);
            if ($product && $product->is_purchasable() && ($product->is_visible() || $product->is_type('variation'))) $out[] = [ $product, max(1, (int)$item['qty']) ];
        }
        return $out;
    }

    private function image($product, $size = 96){
        $id = $product->get_image_id() ?: ($product->get_parent_id() ? wc_get_product($product->get_parent_id())->get_image_id() : 0);
        $src = $id ? wp_get_attachment_image_url($id, 'woocommerce_thumbnail') : wc_placeholder_img_src();
        return '<img src="'.esc_url($src).'" width="'.$size.'" height="'.$size.'" alt="" style="display:block;width:'.$size.'px;height:'.$size.'px;object-fit:contain;border:1px solid #e5e7eb;border-radius:6px;background:#fff">';
    }

    private function link($url){
        return esc_url(add_query_arg([ 'utm_source' => 'email', 'utm_medium' => 'kosaremlekezteto' ], $url));
    }

    function get_content_html(){
        $items = $this->items();
        $token = $this->row->token ?? '';
        $cart_url = $this->link(add_query_arg('vk_cart', $token, home_url('/')));
        $orange = '#f47b20';
        ob_start();
        do_action('woocommerce_email_header', $this->get_heading(), $this);
        echo '<p style="margin:0 0 14px">Szia!</p><p style="margin:0 0 18px">Úgy láttuk, néhány termék a kosaradban maradt. Ha még érdekel, egy kattintással folytathatod a vásárlást – a kosarad tartalmát visszatöltjük.</p>';

        // Termékek
        echo '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 18px">';
        foreach($items as [ $product, $qty ]){
            echo '<tr><td style="padding:10px 12px 10px 0;width:96px;vertical-align:top;border-bottom:1px solid #eee"><a href="'.$this->link($product->get_permalink()).'">'.$this->image($product).'</a></td>'
                .'<td style="padding:10px 0;vertical-align:middle;border-bottom:1px solid #eee"><a href="'.$this->link($product->get_permalink()).'" style="color:#111827;font-weight:600;text-decoration:none">'.esc_html($product->get_name()).'</a>'
                .($qty > 1 ? '<br><span style="color:#6b7280;font-size:13px">'.(int)$qty.' db</span>' : '')
                .'<br><span style="color:'.$orange.';font-weight:700">'.wp_kses_post($product->get_price_html()).'</span></td></tr>';
        }
        echo '</table>';
        echo '<p style="text-align:center;margin:0 0 26px"><a href="'.$cart_url.'" target="_blank" style="display:inline-block;padding:13px 26px;border-radius:6px;background:'.$orange.';color:#ffffff;font-weight:700;text-decoration:none">Vissza a kosárhoz →</a></p>';

        // Miért minket
        $why = [
            [ 'Rugalmas fizetési módok', 'Utánvét, előre utalás, részletfizetés (Cofidis)' ],
            [ 'Szakértő segítség', 'Segítünk megtalálni a megfelelő gépet' ],
            [ 'Megbízható webshop', 'Biztonságos vásárlás, garanciával, szervizháttérrel' ],
        ];
        echo '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 26px;background:#f6f7f9;border-radius:8px"><tr>';
        foreach($why as [ $title, $text ]){
            echo '<td style="width:33%;padding:14px 10px;vertical-align:top;text-align:center;font-size:13px"><strong style="display:block;margin-bottom:4px">'.esc_html($title).'</strong><span style="color:#4b5563">'.esc_html($text).'</span></td>';
        }
        echo '</tr></table>';

        // Ez is érdekelhet
        $ids = [];
        $own = array_map(function($i){ return $i[0]->get_parent_id() ?: $i[0]->get_id(); }, $items);
        foreach($own as $id){
            $product = wc_get_product($id);
            if ($product) $ids = array_merge($ids, $product->get_cross_sell_ids(), wc_get_related_products($id, 6, $own));
        }
        $recs = array_slice(array_values(array_filter(array_map('wc_get_product', array_unique(array_diff($ids, $own))), function($p){ return $p && $p->is_visible() && $p->is_in_stock() && $p->get_price() !== ''; })), 0, 6);
        if ($recs){
            echo '<h2 style="margin:0 0 12px;font-size:18px">Ez is érdekelhet</h2><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 22px">';
            foreach(array_chunk($recs, 3) as $row){
                echo '<tr>';
                foreach($row as $product){
                    echo '<td style="width:33%;padding:6px;vertical-align:top;text-align:center;font-size:12px"><a href="'.$this->link($product->get_permalink()).'" style="color:#111827;text-decoration:none">'
                        .'<span style="display:block;margin:0 auto 6px;width:110px">'.$this->image($product, 110).'</span>'
                        .esc_html(wp_trim_words($product->get_name(), 9, '…')).'</a><br><span style="color:'.$orange.';font-weight:700">'.wp_kses_post($product->get_price_html()).'</span></td>';
                }
                echo str_repeat('<td style="width:33%"></td>', 3 - count($row)).'</tr>';
            }
            echo '</table>';
        }

        // Kategóriák
        $cats = get_terms([ 'taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true, 'exclude' => array_filter([ (int)get_option('default_product_cat') ]) ]);
        if (!is_wp_error($cats) && $cats){
            $links = [];
            foreach($cats as $cat){
                if (in_array($cat->slug, [ 'egyeb', 'uncategorized' ], true)) continue;
                $links[] = '<a href="'.$this->link(get_term_link($cat)).'" style="color:#111827;text-decoration:underline">'.esc_html($cat->name).'</a>';
            }
            if ($links) echo '<p style="text-align:center;font-size:13px;margin:0 0 18px">'.implode(' &nbsp;·&nbsp; ', $links).'</p>';
        }

        echo '<p style="margin:0 0 10px;font-size:12px;color:#6b7280;text-align:center">Ezt a levelet azért kaptad, mert termékeket hagytál a kosaradban a vasskisgep.hu-n. Ha nem szeretnél több ilyen emlékeztetőt, <a href="'.esc_url(add_query_arg('vk_cart_unsub', $token, home_url('/'))).'" style="color:#6b7280">itt leiratkozhatsz</a>.</p>';
        do_action('woocommerce_email_footer', $this);
        return ob_get_clean();
    }

    function get_content_plain(){
        return wp_strip_all_tags(str_replace([ '</p>', '<br>', '</tr>' ], "\n", $this->get_content_html()));
    }
}
