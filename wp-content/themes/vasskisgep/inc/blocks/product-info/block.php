<?php

	iucb_add_block('vasskisgep/product-info', [
		'title' => 'Vásárlási információk',
		'description' => 'Szállítás, személyes átvétel, szerviz és telefonos tanácsadás a termékoldalon.',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Vásárlási információk (szállítás, átvétel, szerviz)";',
		'template' => function(){
			$shipping_url = get_permalink(get_page_by_path('szallitas-es-fizetes'));
			$service_url = get_permalink(get_page_by_path('szerviz'));

			$items = [
				[ 'truck', 'Házhozszállítás futárszolgálattal', 'Országosan, 1-5 munkanap alatt.', $shipping_url ],
				[ 'store', 'Személyes átvétel ingyen', 'Kisvárdai és vásárosnaményi üzletünkben.', $shipping_url ],
				[ 'tool', 'Szakszerviz és alkatrész', 'Üzembe helyezés, karbantartás, javítás.', $service_url ],
				[ 'phone', 'Szakmai tanácsadás', '<a href="tel:+36302895977">+36 30 289 5977</a>', '' ],
			];
			$icons = [
				'truck' => '<path d="M1 3h13v11H1zM14 7h4l3 3v4h-7z" /><circle cx="5.5" cy="17.5" r="2"/><circle cx="17.5" cy="17.5" r="2"/>',
				'store' => '<path d="M3 9l1.5-5h15L21 9M3 9v11h18V9M3 9h18M9 20v-6h6v6"/>',
				'tool' => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>',
				'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
			];

			$html = '<ul class="vk-product-info">';
			foreach($items as [$icon, $title, $text, $url]){
				$html .= '<li><span class="vk-product-info-icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$icons[$icon].'</svg></span>';
				$html .= '<span><strong>'.($url ? '<a href="'.esc_url($url).'">'.$title.'</a>' : $title).'</strong><span>'.$text.'</span></span></li>';
			}
			return $html.'</ul>';
		}
	]);
