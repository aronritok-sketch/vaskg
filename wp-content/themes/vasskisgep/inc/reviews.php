<?php

namespace VasskisgepTheme;

/**
 * Google értékelések a kezdőlapra (Google Places API).
 *
 * Beállítás: Megjelenés → Beállítások → Google értékelések (API kulcs, Place ID,
 * Google profil link). Az adatokat 12 órára gyorsítótárazzuk; ha a lekérés nem
 * sikerül, az utolsó sikeres eredmény jelenik meg. Beállítás nélkül a blokk nem
 * mutat kitalált értékeléseket, csak a Google-profilra mutató linket.
 */
class Reviews {
    const CACHE = 'vasskisgep_google_reviews';
    const LAST_GOOD = 'vasskisgep_google_reviews_last';

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_filter('iu_theme_settings', [$this, 'settings']);
        add_action('update_option_iu_theme', function(){ delete_transient(self::CACHE); });
    }

    function settings($fields){
        $fields['google'] = [
            'type' => 'tab',
            'title' => 'Google értékelések',
            'fields' => [
                'google_api_key' => [
                    'type' => 'password',
                    'label' => 'Google Places API kulcs'
                ],
                'google_place_id' => [
                    'type' => 'text',
                    'label' => 'Place ID (a Google cégprofil azonosítója)'
                ],
                'google_profile_url' => [
                    'type' => 'text',
                    'label' => 'Google cégprofil link (értékelések megtekintése / írása)'
                ]
            ]
        ];
        return $fields;
    }

    static function setting($name){
        if (!function_exists('ius_get')) return '';
        $value = ius_get('iu_theme', $name);
        return is_string($value) ? trim($value) : '';
    }

    /** @return array|null [ 'rating' => float, 'count' => int, 'url' => string, 'reviews' => [...] ] */
    static function get(){
        $key = self::setting('google_api_key');
        $place = self::setting('google_place_id');
        if ($key === '' || $place === '') return null;

        $cached = get_transient(self::CACHE);
        if (is_array($cached)) return $cached;

        $response = wp_remote_get('https://places.googleapis.com/v1/places/'.rawurlencode($place).'?languageCode=hu', [
            'timeout' => 8,
            'headers' => [
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => 'rating,userRatingCount,googleMapsUri,reviews'
            ]
        ]);

        $data = null;
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200){
            $json = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($json['rating'])){
                $reviews = [];
                foreach(($json['reviews'] ?? []) as $review){
                    $text = $review['text']['text'] ?? ($review['originalText']['text'] ?? '');
                    if ((int)($review['rating'] ?? 0) < 4 || trim($text) === '') continue;
                    $reviews[] = [
                        'author' => $review['authorAttribution']['displayName'] ?? '',
                        'rating' => (int)$review['rating'],
                        'text' => $text,
                        'time' => $review['relativePublishTimeDescription'] ?? ''
                    ];
                }
                $data = [
                    'rating' => (float)$json['rating'],
                    'count' => (int)($json['userRatingCount'] ?? 0),
                    'url' => $json['googleMapsUri'] ?? '',
                    'reviews' => array_slice($reviews, 0, 3)
                ];
                update_option(self::LAST_GOOD, $data, false);
            }
        }

        if (!$data) $data = get_option(self::LAST_GOOD) ?: null;
        set_transient(self::CACHE, $data ?: [], $data ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS);
        return $data ?: null;
    }

    static function stars($rating){
        $html = '<span class="vk-stars" aria-label="'.esc_attr(number_format_i18n($rating, 1)).' / 5">';
        for($i = 1; $i <= 5; $i++){
            $fill = max(0, min(1, $rating - ($i - 1)));
            $html .= '<span class="vk-star" style="--vk-star-fill:'.($fill * 100).'%"></span>';
        }
        return $html.'</span>';
    }
}
