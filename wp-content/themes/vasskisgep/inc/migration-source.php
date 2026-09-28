<?php

namespace VasskisgepTheme;

/**
 * Régi webshop (Joomla + VirtueMart) beolvasása: a kategóriaoldalakat bejárva megkeresi a
 * termékoldalakat, és azok adatait (név, cikkszám, ár, akció, készlet, márka, régi kategória,
 * képek, leírás, műszaki adatok, csatolt dokumentumok) átmeneti rekordokba menti
 * (Migration::CPT). A régi oldalon semmit nem módosít, csak a nyilvános oldalakat olvassa.
 */
class MigrationSource {
    const STATE = 'vk_mig_crawl';
    const PER_STEP = 8;
    const PARALLEL = 8;

    /** A régi bolt címe (beállítás), perjel nélkül. */
    static function base(){
        $settings = Migration::settings();
        return untrailingslashit($settings['source'] ?: 'https://vasskisgep.hu');
    }

    static function state(){
        return wp_parse_args((array)get_option(self::STATE, []), [ 'queue' => [], 'seen' => [], 'retries' => [], 'started' => 0, 'finished' => 0, 'categories' => 0, 'products' => 0, 'errors' => [] ]);
    }

    static function reset(){
        update_option(self::STATE, [ 'queue' => [ '/termekek' ], 'seen' => [], 'retries' => [], 'started' => time(), 'finished' => 0, 'categories' => 0, 'products' => 0, 'errors' => [] ], false);
    }

    /** Egy adag oldal feldolgozása. Visszaad: állapot összefoglaló. */
    static function step($limit = self::PER_STEP){
        $state = self::state();
        if (empty($state['started'])){
            self::reset();
            $state = self::state();
        }
        $seen = array_flip($state['seen']);
        $done = 0;
        while ($done < $limit && !empty($state['queue'])){
            // Egy adag (párhuzamosan letöltve).
            $batch = [];
            while (count($batch) < self::PARALLEL && !empty($state['queue'])){
                $path = array_shift($state['queue']);
                if (isset($seen[$path]) || in_array($path, $batch, true)) continue;
                $batch[] = $path;
            }
            if (empty($batch)) break;
            $pages = self::fetch_many($batch);
            foreach($batch as $path){
                $done++;
                $html = $pages[$path];
                if (is_wp_error($html)){
                    // Átmeneti hiba (időtúllépés): legfeljebb háromszor újra, a sor végén.
                    $state['retries'][$path] = ($state['retries'][$path] ?? 0) + 1;
                    if ($state['retries'][$path] < 3) $state['queue'][] = $path;
                    else $state['errors'][] = $path.': '.$html->get_error_message();
                    continue;
                }
                $seen[$path] = true;
                $state['seen'][] = $path;
                if (strpos($html, 'productdetails-view') !== false){
                    $data = self::parse($path, $html);
                    if ($data){
                        Migration::upsert($data);
                        $state['products']++;
                    }
                    continue;
                }
                $state['categories']++;
                foreach(self::links($html) as $link){
                    if (isset($seen[$link]) || in_array($link, $state['queue'], true)) continue;
                    if ($path === '/termekek' || strpos($link, $path.'/') === 0) $state['queue'][] = $link;
                }
            }
        }
        if (empty($state['queue']) && !$state['finished']) $state['finished'] = time();
        $state['errors'] = array_slice($state['errors'], -50);
        update_option(self::STATE, $state, false);
        return [
            'done' => empty($state['queue']),
            'message' => sprintf('Beolvasva: %d kategóriaoldal, %d termékoldal, hátravan: %d oldal', $state['categories'], $state['products'], count($state['queue'])),
            'errors' => count($state['errors'])
        ];
    }

    static function fetch($path){
        $response = wp_remote_get(self::base().$path, [ 'timeout' => 30, 'redirection' => 3, 'user-agent' => 'Mozilla/5.0 (VassKisgep atkoltoztetes)' ]);
        if (is_wp_error($response)) return $response;
        $code = (int)wp_remote_retrieve_response_code($response);
        if ($code !== 200) return new \WP_Error('http', 'HTTP '.$code);
        return (string)wp_remote_retrieve_body($response);
    }

    /** Több oldal párhuzamos letöltése (a WordPress proxy- és tanúsítvány-beállításaival). */
    static function fetch_many(array $paths){
        $out = [];
        if (!class_exists('\\WpOrg\\Requests\\Requests') || count($paths) === 1){
            foreach($paths as $path) $out[$path] = self::fetch(self::with_limit($path));
            return $out;
        }
        $requests = [];
        foreach($paths as $path) $requests[$path] = [ 'url' => self::base().self::with_limit($path), 'type' => 'GET' ];
        $args = apply_filters('http_request_args', [ 'sslcertificates' => ABSPATH.WPINC.'/certificates/ca-bundle.crt', 'sslverify' => true ], self::base());
        $options = [ 'timeout' => 30, 'connect_timeout' => 15, 'useragent' => 'Mozilla/5.0 (VassKisgep atkoltoztetes)', 'follow_redirects' => true, 'redirects' => 3, 'verify' => $args['sslverify'] ? $args['sslcertificates'] : false ];
        $proxy = new \WP_HTTP_Proxy();
        if ($proxy->is_enabled() && $proxy->send_through_proxy(self::base())){
            $options['proxy'] = new \WpOrg\Requests\Proxy\Http($proxy->host().':'.$proxy->port());
        }
        try {
            $responses = \WpOrg\Requests\Requests::request_multiple($requests, $options);
        } catch (\Throwable $e){
            foreach($paths as $path) $out[$path] = self::fetch(self::with_limit($path));
            return $out;
        }
        foreach($paths as $path){
            $response = $responses[$path] ?? null;
            if ($response instanceof \WpOrg\Requests\Response && $response->status_code === 200) $out[$path] = (string)$response->body;
            elseif ($response instanceof \WpOrg\Requests\Response) $out[$path] = new \WP_Error('http', 'HTTP '.$response->status_code);
            else $out[$path] = new \WP_Error('http', $response instanceof \Throwable ? $response->getMessage() : 'letöltési hiba');
        }
        return $out;
    }

    private static function with_limit($path){
        return $path.(strpos($path, '?') === false ? '?' : '&').'limit=1000';
    }

    /** A /termekek/ alatti belső linkek (kategóriák és termékek). */
    static function links($html){
        preg_match_all('~href="(/termekek/[^"?#]+)"~', $html, $m);
        $links = [];
        foreach(array_unique($m[1]) as $link){
            if (strpos($link, '/manufacturer/') !== false || strpos($link, 'askquestion') !== false || strpos($link, '/dirDesc') !== false) continue;
            $links[] = html_entity_decode($link, ENT_QUOTES, 'UTF-8');
        }
        return $links;
    }

    private static function text($html){
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags((string)$html), ENT_QUOTES, 'UTF-8')));
    }

    private static function absolute($url){
        $url = html_entity_decode(trim($url), ENT_QUOTES, 'UTF-8');
        if ($url === '' || preg_match('~^(https?:)?//~', $url)) return $url;
        return self::base().'/'.ltrim($url, '/');
    }

    /** Egy VirtueMart termékoldal adatai. */
    static function parse($path, $html){
        if (!preg_match('/name="virtuemart_product_id\[\]" value="(\d+)"/', $html, $m)) return null;
        $d = [ 'id' => (int)$m[1], 'url' => self::base().$path, 'path_url' => $path ];

        $d['name'] = preg_match('~<h1 class="product-title"[^>]*>(.*?)</h1>~s', $html, $m) ? self::text($m[1]) : '';
        foreach([ 'price', 'sku', 'availability', 'brand' ] as $key){
            $d[$key] = preg_match('~<meta itemprop="'.$key.'" content="([^"]*)"~', $html, $m) ? trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8')) : '';
        }
        $d['regular'] = preg_match('~<span class="PricebasePriceWithTax">([^<]*)</span>~', $html, $m) ? preg_replace('/\D/', '', $m[1]) : '';

        $d['path'] = [];
        if (preg_match('~<ul class="breadcrumb[^"]*">(.*?)</ul>~s', $html, $m)){
            preg_match_all('~<a class="hover-invert" href="[^"]*">(.*?)</a>~s', $m[1], $crumbs);
            $d['path'] = array_values(array_slice(array_map([__CLASS__, 'text'], $crumbs[1]), 2));
        }

        $d['stock_text'] = preg_match('~<div class="product-stock-cont[^"]*">\s*<span[^>]*>(.*?)</span>~s', $html, $m) ? self::text($m[1]) : '';

        preg_match_all('~<a href="(/images/virtuemart/product/[^"]+)"~', $html, $m);
        $d['images'] = array_values(array_unique(array_map([__CLASS__, 'absolute'], $m[1])));

        $d['fields'] = [];
        if (preg_match_all('~<div class="product-field product-field-type-\w+ [^"]*">.*?<span class="product-fields-title">(.*?)</span>.*?<div class="product-field-display[^"]*">(.*?)</div>\s*</div>~s', $html, $m, PREG_SET_ORDER)){
            foreach($m as $field){
                $value = preg_replace('~<div class="product-fields-title-wrapper[^"]*">\s*</div>~', '', $field[2]);
                $d['fields'][self::text($field[1])] = self::text($value);
            }
        }

        $d['description'] = preg_match('~id="tab-product-desc">\s*<div class="product-tab-texts[^"]*">\s*<div class="description">(.*?)</div>\s*</div>\s*</div>~s', $html, $m) ? trim($m[1]) : '';

        // Csatolt dokumentumok (a „Kapcsolódó mellékletek” fül, JavaScriptből beillesztve).
        $d['documents'] = [];
        if (preg_match('~id=\\\\"tab-attachments\\\\">(.*?)"\);~s', $html, $m)){
            preg_match_all('~href=\\\\"([^"\\\\]+)\\\\"~', $m[1], $docs);
            foreach($docs[1] as $doc){
                $doc = self::absolute(stripslashes($doc));
                if (preg_match('/\.pdf$/i', $doc)) $d['documents'][] = $doc;
            }
            $d['documents'] = array_values(array_unique($d['documents']));
        }
        return $d['name'] !== '' ? $d : null;
    }
}
