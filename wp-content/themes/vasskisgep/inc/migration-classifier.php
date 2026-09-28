<?php

namespace VasskisgepTheme;

/**
 * Besorolás az új kategóriafába.
 *
 * 1. Megfeleltetés: setup/migration/category-map.json „rules” – [régi útvonal előtag, új levélkategória,
 *    ellenőrizendő?]; a leghosszabb egyező előtag dönt. A vegyes régi kategóriák ellenőrizendők.
 * 2. Claude (Anthropic Messages API, eszközhívás felsorolt kategórialistával): az ellenőrizendő
 *    termékek 10-es adagokban; a küszöb feletti megbízhatóság jóváhagyva, alatta kézi ellenőrzés.
 *    API-kulcs: wp-config.php → define('VASSKISGEP_ANTHROPIC_API_KEY', '…'), vagy a beállításoknál.
 */
class MigrationClassifier {
    const BATCH = 10;
    const TOOL = 'record_categories';

    private static $map;
    private static $leaves;

    static function map(){
        if (self::$map === null) self::$map = json_decode(Setup::content_file('migration/category-map.json'), true) ?: [ 'rules' => [], 'brands' => [], 'fields' => [], 'local_fields' => [] ];
        return self::$map;
    }

    /** Az új fa levélkategóriái: név => [term_id, útvonal]. */
    static function leaves(){
        if (self::$leaves !== null) return self::$leaves;
        $catalog = json_decode(Setup::content_file('catalog/categories.json'), true);
        $leaves = [];
        $walk = function($nodes, $path) use (&$walk, &$leaves){
            foreach($nodes as $node){
                [$name, $children] = is_array($node) ? $node : [$node, []];
                $full = ltrim($path.'/'.$name, '/');
                if (!empty($children)){ $walk($children, $full); continue; }
                $term = Catalog::category_by_path($full);
                if ($term) $leaves[$name] = [ (int)$term->term_id, str_replace('/', ' › ', $full) ];
            }
        };
        $walk($catalog['tree'], '');
        return self::$leaves = $leaves;
    }

    static function term_options(){
        $html = '';
        $group = '';
        foreach(self::leaves() as $name => [$id, $path]){
            $parts = explode(' › ', $path);
            if ($parts[0] !== $group){
                if ($group !== '') $html .= '</optgroup>';
                $group = $parts[0];
                $html .= '<optgroup label="'.esc_attr($group).'">';
            }
            $html .= '<option value="'.(int)$id.'">'.esc_html(implode(' › ', array_slice($parts, 1))).'</option>';
        }
        return $html.($group !== '' ? '</optgroup>' : '');
    }

    /** A megfeleltetés egy termékre: [levélnév, ellenőrizendő]. */
    static function match(array $data){
        $path = mb_strtolower(implode(' > ', (array)($data['path'] ?? [])));
        $best = null;
        foreach(self::map()['rules'] as [$prefix, $leaf, $check]){
            $prefix = mb_strtolower($prefix);
            if ($prefix !== '' && strpos($path, $prefix) === 0 && (!$best || mb_strlen($prefix) > mb_strlen($best[0]))) $best = [$prefix, $leaf, (bool)$check];
        }
        return $best ? [ $best[1], $best[2] ] : [ '', true ];
    }

    /** Az új (még be nem sorolt) rekordok besorolása a megfeleltetés alapján. */
    static function apply_map($limit = 400){
        $leaves = self::leaves();
        $ids = Migration::ids('new', $limit);
        $ready = $check = 0;
        foreach($ids as $id){
            $data = Migration::data($id);
            [$leaf, $unsure] = self::match($data);
            $note = '';
            if (!empty($data['fields']['Méret']) && stripos($data['fields']['Méret'], 'Válasszon') !== false){
                $unsure = true;
                $note = 'Méretválasztós termék a régi boltban – egyszerű termékként jön át';
            }
            if ((float)($data['price'] ?? 0) <= 0) $note = trim($note.' Nincs ár a régi boltban (az új boltban nem lesz megvásárolható, amíg nem kap árat).');
            $suggestion = [ 'term' => $leaves[$leaf][0] ?? 0, 'source' => 'megfeleltetés', 'confidence' => $unsure ? 0.6 : 0.95, 'note' => $note ];
            $status = !$suggestion['term'] ? 'review' : ($unsure ? 'check' : 'ready');
            if ($note !== '' && $status === 'ready') $status = 'review';
            Migration::set_suggestion($id, $suggestion, $status);
            $status === 'ready' ? $ready++ : $check++;
        }
        $left = count(Migration::ids('new', 1));
        return [ 'done' => !$left, 'message' => sprintf('%d jóváhagyva a megfeleltetés alapján, %d ellenőrizendő (Claude / kézi)', $ready, $check) ];
    }

    /* ---------- Claude ---------- */

    static function key(){
        return defined('VASSKISGEP_ANTHROPIC_API_KEY') ? (string)VASSKISGEP_ANTHROPIC_API_KEY : (string)Migration::settings()['api_key'];
    }

    /** Becsült költség USD-ben (Sonnet: 3 / 15 USD per millió token, gyorsítótárazott rendszerüzenettel). */
    static function estimate($count){
        $requests = (int)ceil($count / self::BATCH);
        $input = $requests * (self::BATCH * 450 + 400) + $requests * 4000 * 0.1;
        $output = $count * 60;
        return number_format_i18n(($input * 3 + $output * 15) / 1000000, 2);
    }

    static function system_prompt(){
        $lines = [
            'Egy magyar kertigép-, erdészeti és építőipari kisgép-webáruház (Vass Kisgép; márkák: STIHL, STIGA, Cedrus, Eurosystems, Grillo, Bluebird, Rato, Kasei stb.) termékeit sorolod be az ÚJ kategóriafába a régi webshopból való átköltöztetéskor.',
            'Szabályok:',
            '- Minden termékhez pontosan egy levélkategóriát választasz a lenti listából (a pontos nevét adod meg).',
            '- A régi kategória márka szerinti volt és vegyes (gépek és tartozékok keveredtek): a termék neve és leírása dönt. Géphez a gép kategóriáját, tartozékhoz, alkatrészhez, akkumulátorhoz, ruházathoz a megfelelő tartozék- vagy alkatrész-kategóriát válaszd.',
            '- Akkumulátor és töltő önállóan: „Akkumulátorok” vagy „Töltők”; hordtáska, tápegység, adapter akkus rendszerhez: „Akkus rendszer kiegészítők”. Gép akkuval szettben: a gép kategóriája.',
            '- A „suggested” mező a régi kategória alapján adott előzetes javaslat; ha megfelel, maradhat.',
            '- confidence (0–1): mennyire biztos a választás. 0.9 felett, ha a név alapján egyértelmű; 0.7 alatt, ha két kategória is szóba jöhet. Ne találgass: bizonytalan esetben alacsony értéket adj, és a note mezőben röviden (magyarul) írd le, miért.',
            '',
            'Az új kategóriafa levélkategóriái (Főkategória › Oszlop › Csoport › Levél):',
        ];
        foreach(self::leaves() as $name => [$id, $path]) $lines[] = '- '.$path;
        return implode("\n", $lines);
    }

    static function tool(){
        return [
            'name' => self::TOOL,
            'description' => 'A termékek besorolása az új kategóriafába.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => [ 'type' => 'integer' ],
                                'category' => [ 'type' => 'string', 'enum' => array_keys(self::leaves()) ],
                                'confidence' => [ 'type' => 'number' ],
                                'note' => [ 'type' => 'string' ]
                            ],
                            'required' => [ 'id', 'category', 'confidence' ]
                        ]
                    ]
                ],
                'required' => [ 'items' ]
            ]
        ];
    }

    private static function payload($id, array $data){
        $leaves = self::leaves();
        $suggestion = Migration::suggestion($id);
        $suggested = '';
        foreach($leaves as $name => [$term_id]){
            if ($term_id === (int)($suggestion['term'] ?? 0)) $suggested = $name;
        }
        return [
            'id' => $id,
            'name' => $data['name'] ?? '',
            'brand' => $data['brand'] ?? '',
            'old_category' => implode(' > ', (array)($data['path'] ?? [])),
            'specs' => (object)array_filter((array)($data['fields'] ?? []), function($v){ return mb_strlen($v) < 80; }),
            'description' => mb_substr(trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string)($data['description'] ?? '')))), 0, 500),
            'suggested' => $suggested
        ];
    }

    /** Egy adag Claude-besorolás a „check” állapotú rekordokra. */
    static function ai_step(){
        $key = self::key();
        if ($key === '') return new \WP_Error('key', 'Nincs beállítva Anthropic API-kulcs.');
        $ids = Migration::ids('check', self::BATCH);
        if (empty($ids)) return [ 'done' => true, 'message' => 'Nincs Claude-ra váró termék.' ];

        $products = [];
        foreach($ids as $id) $products[] = self::payload($id, Migration::data($id));

        $settings = Migration::settings();
        $body = [
            'model' => $settings['model'] ?: 'claude-sonnet-5',
            'max_tokens' => 3000,
            'system' => [[ 'type' => 'text', 'text' => self::system_prompt(), 'cache_control' => [ 'type' => 'ephemeral' ] ]],
            'tools' => [ self::tool() ],
            'tool_choice' => [ 'type' => 'tool', 'name' => self::TOOL ],
            'messages' => [[ 'role' => 'user', 'content' => "Sorold be ezeket a termékeket:\n".wp_json_encode($products, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ]]
        ];
        $response = wp_remote_post(apply_filters('vasskisgep_migration_ai_endpoint', 'https://api.anthropic.com/v1/messages'), [
            'timeout' => 90,
            'headers' => [ 'x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json' ],
            'body' => wp_json_encode($body)
        ]);
        if (is_wp_error($response)) return new \WP_Error('http', $response->get_error_message(), [ 'retry_after' => 20 ]);
        $code = (int)wp_remote_retrieve_response_code($response);
        $data = json_decode((string)wp_remote_retrieve_body($response), true);
        if ($code === 429 || $code === 529 || $code >= 500){
            return new \WP_Error('busy', 'Az API túlterhelt vagy korlátozott ('.$code.').', [ 'retry_after' => max(10, (int)wp_remote_retrieve_header($response, 'retry-after')) ]);
        }
        if ($code !== 200 || !is_array($data)) return new \WP_Error('api', 'API hiba ('.$code.'): '.mb_substr((string)($data['error']['message'] ?? wp_remote_retrieve_body($response)), 0, 300));

        $items = [];
        foreach((array)($data['content'] ?? []) as $block){
            if (($block['type'] ?? '') === 'tool_use' && ($block['name'] ?? '') === self::TOOL) $items = (array)($block['input']['items'] ?? []);
        }
        $leaves = self::leaves();
        $threshold = (float)$settings['threshold'];
        $ready = $review = 0;
        $answered = [];
        foreach($items as $item){
            $id = (int)($item['id'] ?? 0);
            if (!in_array($id, $ids, true) || empty($leaves[$item['category'] ?? ''])) continue;
            $answered[] = $id;
            $confidence = max(0, min(1, (float)($item['confidence'] ?? 0)));
            $previous = Migration::suggestion($id);
            $suggestion = [ 'term' => $leaves[$item['category']][0], 'source' => 'claude', 'confidence' => $confidence, 'note' => trim((string)($item['note'] ?? '')) ?: ($previous['note'] ?? '') ];
            $ok = $confidence >= $threshold && strpos((string)($previous['note'] ?? ''), 'Méretválasztós') === false && strpos((string)($previous['note'] ?? ''), 'Nincs ár') === false;
            Migration::set_suggestion($id, $suggestion, $ok ? 'ready' : 'review');
            $ok ? $ready++ : $review++;
        }
        // Amire nem jött érvényes válasz: kézi ellenőrzés (a korábbi javaslattal).
        foreach(array_diff($ids, $answered) as $id){
            Migration::set_suggestion($id, Migration::suggestion($id), 'review');
            $review++;
        }
        $usage = $data['usage'] ?? [];
        $left = count(Migration::ids('check', 1));
        return [ 'done' => !$left, 'message' => sprintf('Claude: %d jóváhagyva, %d ellenőrizendő (tokenek: %d be, %d ki)', $ready, $review, (int)($usage['input_tokens'] ?? 0) + (int)($usage['cache_read_input_tokens'] ?? 0), (int)($usage['output_tokens'] ?? 0)) ];
    }
}
