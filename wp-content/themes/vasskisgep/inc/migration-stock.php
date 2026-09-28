<?php

namespace VasskisgepTheme;

/**
 * Készlet betöltése CSV-ből. A régi bolt nyilvános oldalain csak a készletállapot látszik
 * (raktáron / visszaigazolástól 1-5 munkanap), a darabszám a régi adatbázisban van
 * (VirtueMart: …_virtuemart_products.product_in_stock). A CSV-t a tárhely phpMyAdminjából
 * lehet menteni, vagy bármilyen táblázatból, amiben van azonosító vagy cikkszám és készlet.
 *
 * A már importált termékeken azonnal beállítja a készletet, a még nem importáltakhoz
 * megjegyzi (az import alkalmazza). Nulla készletnél a termék rendelhető marad
 * („visszaigazolástól 1-5 munkanap”), ahogy a régi boltban.
 */
class MigrationStock {
    const OPTION = 'vk_mig_stock';

    const COLUMNS = [
        'id' => [ 'virtuemart_product_id', 'product_id', 'id', 'azonosito', 'regi azonosito', 'termek azonosito' ],
        'sku' => [ 'product_sku', 'sku', 'cikkszam', 'cikk szam', 'termekkod', 'kod' ],
        'qty' => [ 'product_in_stock', 'in_stock', 'stock', 'keszlet', 'mennyiseg', 'db', 'raktarkeszlet' ],
        'ordered' => [ 'product_ordered', 'ordered', 'foglalt', 'lefoglalt' ],
    ];

    static function stored(){
        return wp_parse_args((array)get_option(self::OPTION, []), [ 'id' => [], 'sku' => [], 'updated' => 0 ]);
    }

    private static function key($label){
        $label = strtolower(remove_accents(trim((string)$label, " \t\"'\xEF\xBB\xBF")));
        return trim(preg_replace('/[^a-z0-9_]+/', ' ', $label));
    }

    /** CSV beolvasása: elválasztó (; , tab) és kódolás (UTF-8 / Windows-1250) felismerése. */
    static function parse($file){
        $content = (string)file_get_contents($file);
        if ($content === '') return new \WP_Error('empty', 'Üres fájl.');
        if (!mb_check_encoding($content, 'UTF-8')){
            $converted = function_exists('iconv') ? @iconv('Windows-1250', 'UTF-8//IGNORE', $content) : false;
            $content = $converted !== false ? $converted : mb_convert_encoding($content, 'UTF-8', 'ISO-8859-2');
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $first = $lines[0];
        $delimiter = ';';
        foreach([ "\t", ';', ',' ] as $candidate){
            if (substr_count($first, $candidate) > substr_count($first, $delimiter)) $delimiter = $candidate;
        }
        $header = array_map([__CLASS__, 'key'], str_getcsv($first, $delimiter, '"', ''));
        $columns = [];
        foreach(self::COLUMNS as $name => $labels){
            foreach($header as $index => $label){
                if (in_array($label, $labels, true)){ $columns[$name] = $index; break; }
            }
        }
        if (!isset($columns['qty']) || (!isset($columns['id']) && !isset($columns['sku']))){
            return new \WP_Error('columns', 'A CSV első sorában oszlopnevek kellenek: készlet (product_in_stock / készlet) és azonosító (virtuemart_product_id) vagy cikkszám (product_sku / cikkszám). Talált oszlopok: '.implode(', ', $header));
        }
        $rows = [];
        foreach(array_slice($lines, 1) as $line){
            if (trim($line) === '') continue;
            $cells = str_getcsv($line, $delimiter, '"', '');
            $qty = str_replace([ ' ', ',' ], [ '', '.' ], (string)($cells[$columns['qty']] ?? ''));
            if (!is_numeric($qty)) continue;
            $ordered = isset($columns['ordered']) ? (float)str_replace(',', '.', (string)($cells[$columns['ordered']] ?? 0)) : 0;
            $rows[] = [
                'id' => isset($columns['id']) ? (int)($cells[$columns['id']] ?? 0) : 0,
                'sku' => isset($columns['sku']) ? trim((string)($cells[$columns['sku']] ?? '')) : '',
                'qty' => max(0, (int)floor((float)$qty - $ordered)),
            ];
        }
        return $rows;
    }

    /** A sorok eltárolása és alkalmazása a már importált termékekre. */
    static function load(array $rows){
        global $wpdb;
        $stored = self::stored();
        $imported = [];
        foreach($wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_vk_old_id'") as $row){
            if (get_post_type($row->post_id) === 'product') $imported[(int)$row->meta_value] = (int)$row->post_id;
        }
        $staged = [];
        foreach(Migration::ids() as $post_id){
            $data = Migration::data($post_id);
            if (!empty($data['id'])) $staged[(int)$data['id']] = trim((string)($data['sku'] ?? ''));
        }
        $staged_skus = array_flip(array_filter($staged));

        $updated = $pending = 0;
        $missing = [];
        foreach($rows as $row){
            $product_id = 0;
            $known = false;
            if ($row['id']){
                $stored['id'][$row['id']] = $row['qty'];
                $product_id = $imported[$row['id']] ?? 0;
                $known = $product_id || isset($staged[$row['id']]);
            }
            if ($row['sku'] !== ''){
                $stored['sku'][$row['sku']] = $row['qty'];
                if (!$product_id) $product_id = (int)wc_get_product_id_by_sku($row['sku']);
                $known = $known || $product_id || isset($staged_skus[$row['sku']]);
            }
            if ($product_id && ($product = wc_get_product($product_id))){
                self::set($product, $row['qty']);
                $product->save();
                $updated++;
            } elseif ($known){
                $pending++;
            } else {
                $missing[] = $row['sku'] !== '' ? $row['sku'] : '#'.$row['id'];
            }
        }
        $stored['updated'] = time();
        update_option(self::OPTION, $stored, false);
        return [ 'updated' => $updated, 'pending' => $pending, 'missing' => $missing ];
    }

    static function set(\WC_Product $product, $qty){
        $product->set_manage_stock(true);
        $product->set_stock_quantity((int)$qty);
        // Elfogyott termék: rendelhető, „visszaigazolástól 1-5 munkanap” (mint a régi boltban).
        $product->set_backorders('notify');
    }

    /** Import közben: a CSV-ből ismert készlet (régi azonosító, majd cikkszám alapján). */
    static function apply(\WC_Product $product, array $data){
        $stored = self::stored();
        $sku = trim((string)($data['sku'] ?? ''));
        $qty = $stored['id'][(int)($data['id'] ?? 0)] ?? ($sku !== '' ? ($stored['sku'][$sku] ?? null) : null);
        if ($qty === null) return false;
        self::set($product, $qty);
        return true;
    }

    static function message(array $result){
        $message = sprintf('Készlet beállítva: %d termék; az import során kap készletet: %d termék.', $result['updated'], $result['pending']);
        if (!empty($result['missing'])) $message .= sprintf(' Nem található: %d sor (%s%s).', count($result['missing']), implode(', ', array_slice($result['missing'], 0, 10)), count($result['missing']) > 10 ? ', …' : '');
        return $message;
    }
}
