<?php

	iucb_add_block('vasskisgep/google-reviews', [
		'title' => 'Google értékelések',
		'description' => 'Átlagos értékelés és a legutóbbi vélemények a Google cégprofilról (Megjelenés → Beállítások → Google értékelések).',
		'category' => 'widgets',
		'attributes' => [
			'title' => [
				'type' => 'string',
				'default' => 'Vásárlóink véleménye'
			],
			'text' => [
				'type' => 'string',
				'default' => 'Valódi tapasztalatok vásárlóinktól gépekről, kiszolgálásról.'
			]
		],
		'fields' => [
			[
				'panel' => 'Beállítások',
				'fields' => [
					'title' => [
						'type' => 'text',
						'label' => 'Cím'
					],
					'text' => [
						'type' => 'textarea',
						'label' => 'Bevezető'
					]
				]
			]
		],
		'editJS' => 'return "Google értékelések";',
		'template' => function($attributes){
			$data = \VasskisgepTheme\Reviews::get();
			$profile = \VasskisgepTheme\Reviews::setting('google_profile_url');
			if ($data && !empty($data['url']) && $profile === '') $profile = $data['url'];

			$html = '<div class="vk-reviews">';
			$html .= '<div class="vk-reviews-head">';
				$html .= '<div><h2 class="iu-heading">'.esc_html($attributes['title']).'</h2>';
				if (!empty($attributes['text'])) $html .= '<p class="iu-text">'.esc_html($attributes['text']).'</p>';
				$html .= '</div>';

				if ($data){
					$html .= '<div class="vk-reviews-summary">';
						$html .= '<div class="vk-reviews-rating"><strong>'.esc_html(number_format_i18n($data['rating'], 1)).'</strong> / 5 '.\VasskisgepTheme\Reviews::stars($data['rating']).'</div>';
						$html .= '<p class="iu-text iu-text-small iu-text-muted">'.esc_html(number_format_i18n($data['count'])).' Google vélemény alapján</p>';
					$html .= '</div>';
				}
			$html .= '</div>';

			if ($data && !empty($data['reviews'])){
				$html .= '<div class="vk-reviews-list">';
				foreach($data['reviews'] as $review){
					$html .= '<figure class="vk-review">'
						.\VasskisgepTheme\Reviews::stars($review['rating'])
						.'<blockquote class="iu-text">'.esc_html(wp_trim_words($review['text'], 40, '…')).'</blockquote>'
						.'<figcaption><strong>'.esc_html($review['author']).'</strong>'.($review['time'] ? ' <span>'.esc_html($review['time']).'</span>' : '').'</figcaption>'
					.'</figure>';
				}
				$html .= '</div>';
			}

			if ($profile !== ''){
				$html .= '<div class="iu-button-group vk-reviews-actions">'
					.'<a class="iu-button iu-button-default" href="'.esc_url($profile).'" target="_blank" rel="noopener"><span class="iu-button-text"><span>'.($data ? 'Összes vélemény a Google-on' : 'Véleményeink a Google-on').'</span></span></a>'
				.'</div>';
			}

			return $html.'</div>';
		},
		'style' => [ 'iu-button-style' ]
	]);
