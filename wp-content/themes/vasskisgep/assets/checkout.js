/*
 * Pénztár / címszerkesztés:
 *  – irányítószám → település: 4 számjegy után kitölti a várost; ha több település tartozik hozzá, választólistát mutat,
 *  – adószám → cégnév és székhely a NAV-ból (ha a bolt beállította, lásd inc/checkout.php).
 */
(function($){
	'use strict';
	var cfg = window.vasskisgepCheckout || {};
	var postcodes = null, loading = null;

	function loadPostcodes(){
		if (postcodes) return $.Deferred().resolve(postcodes).promise();
		if (!loading) loading = $.getJSON(cfg.postcodes).then(function(data){ postcodes = data; return data; });
		return loading;
	}

	function isHungary(prefix){
		var $country = $('#' + prefix + '_country');
		return !$country.length || !$country.val() || $country.val() === 'HU';
	}

	function setValue($field, value){
		if (!$field.length || $field.val() === value) return;
		$field.val(value).trigger('input').trigger('change');
	}

	function pickList(prefix){
		var $row = $('#' + prefix + '_city_field');
		var $list = $row.find('.vk-city-pick');
		if (!$list.length) $list = $('<div class="vk-city-pick" role="group" aria-label="Település választása"></div>').appendTo($row);
		return $list;
	}

	function onPostcode(prefix){
		var $postcode = $('#' + prefix + '_postcode'), $city = $('#' + prefix + '_city');
		if (!$postcode.length || !$city.length) return;
		var code = ($postcode.val() || '').replace(/\D/g, '');
		var $list = pickList(prefix);
		if (code.length !== 4 || !isHungary(prefix)){ $list.empty().hide(); return; }
		loadPostcodes().then(function(data){
			var cities = data[code] || [];
			$list.empty().hide();
			if (!cities.length) return;
			var current = $.trim($city.val());
			var auto = $city.data('vkAuto');
			if (cities.length === 1){
				// Csak üres vagy általunk kitöltött mezőt írunk át.
				if (current === '' || current === auto){ setValue($city, cities[0]); $city.data('vkAuto', cities[0]); }
				return;
			}
			if ($.inArray(current, cities) === -1 && current === auto){ setValue($city, ''); $city.data('vkAuto', ''); }
			$list.append('<span class="vk-city-pick-label">Ehhez az irányítószámhoz több település tartozik:</span>');
			$.each(cities, function(i, name){
				$('<button type="button" class="vk-city-pick-option"></button>').text(name)
					.toggleClass('vk-active', name === $.trim($city.val()))
					.on('click', function(){
						setValue($city, name); $city.data('vkAuto', name);
						$list.find('.vk-city-pick-option').removeClass('vk-active'); $(this).addClass('vk-active');
					})
					.appendTo($list);
			});
			$list.show();
		});
	}

	/* ---------- Adószám → cégadatok ---------- */

	var lastTax = '';
	function taxStatus(text, type){
		var $row = $('#billing_tax_number_field');
		var $status = $row.find('.vk-tax-status');
		if (!$status.length) $status = $('<p class="vk-tax-status" aria-live="polite"></p>').appendTo($row);
		$status.text(text || '').attr('data-type', type || '').toggle(!!text);
	}

	function onTaxNumber(){
		if (!cfg.taxpayer) return;
		var digits = ($('#billing_tax_number').val() || '').replace(/\D/g, '');
		if (digits.length < 8){ lastTax = ''; taxStatus(''); return; }
		digits = digits.slice(0, 8);
		if (digits === lastTax) return;
		lastTax = digits;
		taxStatus('Cégadatok lekérdezése a NAV-tól…', 'loading');
		$.post(cfg.ajax_url, { action: 'vasskisgep_taxpayer', nonce: cfg.nonce, tax_number: digits }).done(function(res){
			if (digits !== lastTax) return;
			if (!res || !res.success){
				var msg = res && res.data && res.data.message;
				taxStatus(msg && msg !== 'disabled' ? msg : '', 'error');
				return;
			}
			var d = res.data;
			setValue($('#billing_company'), d.company);
			if (d.tax_number && d.tax_number.length > 8) setValue($('#billing_tax_number'), d.tax_number);
			// A címet csak üres mezőkbe írjuk (a vásárló már beírt címét nem írjuk felül).
			var empty = !$.trim($('#billing_postcode').val()) && !$.trim($('#billing_city').val()) && !$.trim($('#billing_address_1').val());
			if (empty && d.postcode){
				setValue($('#billing_postcode'), d.postcode);
				setValue($('#billing_city'), d.city);
				$('#billing_city').data('vkAuto', d.city);
				setValue($('#billing_address_1'), d.address);
			}
			taxStatus('✓ ' + d.company + (empty && d.postcode ? ' – székhely kitöltve' : ''), 'ok');
		}).fail(function(){ if (digits === lastTax) taxStatus('A lekérdezés nem sikerült, kérjük, töltse ki kézzel.', 'error'); });
	}

	/* ---------- Telefonszám: országhívószám-választó (ügyfélkérés, 2026-11) ---------- */
	// Az iu_woocommerce gépelés közbeni maszkja helyett (beillesztés / automatikus kitöltés után a kurzor ugrált,
	// a „06 …” alakból „+06 …” lett): ország + hívószám választó, a szám szabadon írható, kilépéskor rendeződik.
	var DIALS = [
		['HU', '🇭🇺', 'Magyarország', '+36'], ['SK', '🇸🇰', 'Szlovákia', '+421'], ['RO', '🇷🇴', 'Románia', '+40'],
		['UA', '🇺🇦', 'Ukrajna', '+380'], ['AT', '🇦🇹', 'Ausztria', '+43'], ['RS', '🇷🇸', 'Szerbia', '+381'],
		['HR', '🇭🇷', 'Horvátország', '+385'], ['SI', '🇸🇮', 'Szlovénia', '+386'], ['CZ', '🇨🇿', 'Csehország', '+420'],
		['PL', '🇵🇱', 'Lengyelország', '+48'], ['DE', '🇩🇪', 'Németország', '+49'], ['IT', '🇮🇹', 'Olaszország', '+39'],
		['GB', '🇬🇧', 'Egyesült Királyság', '+44']
	];
	var BY_LENGTH = DIALS.slice().sort(function(a, b){ return b[3].length - a[3].length; });

	function splitPhone(value){
		var raw = $.trim(value || '');
		var digits = raw.replace(/\D/g, '');
		if (!digits) return { dial: '+36', national: '' };
		if (raw.charAt(0) === '+' || digits.indexOf('00') === 0){
			if (digits.indexOf('00') === 0 && raw.charAt(0) !== '+') digits = digits.slice(2);
			for (var i = 0; i < BY_LENGTH.length; i++){
				var code = BY_LENGTH[i][3].slice(1);
				if (digits.indexOf(code) === 0) return { dial: BY_LENGTH[i][3], national: digits.slice(code.length) };
			}
			return { dial: '', national: '+' + digits };
		}
		if (digits.indexOf('06') === 0) return { dial: '+36', national: digits.slice(2) };
		if (digits.indexOf('36') === 0 && digits.length >= 11) return { dial: '+36', national: digits.slice(2) };
		return { dial: null, national: digits };
	}

	function formatNational(dial, digits){
		digits = (digits || '').replace(/\D/g, '').replace(/^0+/, '');
		if (dial === '+36'){
			if (digits.charAt(0) === '1') return $.trim(digits.slice(0, 1) + ' ' + digits.slice(1, 4) + ' ' + digits.slice(4));
			return $.trim(digits.slice(0, 2) + ' ' + digits.slice(2, 5) + ' ' + digits.slice(5));
		}
		return digits.replace(/(\d{3})(?=\d)/g, '$1 ');
	}

	function setupPhone(input){
		var $input = $(input);
		if ($input.data('vkPhone')) return;
		$input.data('vkPhone', true).off('input').attr({ placeholder: '30 123 4567', autocomplete: 'tel-national' });
		var $select = $('<select class="vk-phone-dial" aria-label="Ország hívószáma"></select>');
		$.each(DIALS, function(i, d){ $select.append($('<option></option>').val(d[3]).text(d[1] + ' ' + d[3]).attr('title', d[2])); });
		$select.append('<option value="">Egyéb (+…)</option>');
		$input.wrap('<span class="vk-phone"></span>').before($select);

		function apply(value){
			var parts = splitPhone(value);
			if (parts.dial !== null) $select.val(parts.dial);
			var dial = $select.val();
			$input.val(dial ? formatNational(dial, parts.national) : parts.national);
		}
		apply($input.val());
		$input.on('blur paste', function(e){
			var el = this;
			setTimeout(function(){ apply(el.value); }, e.type === 'paste' ? 0 : 1);
		});
		// Beküldés előtt a teljes, nemzetközi szám kerül a mezőbe; hiba után újra szétválik.
		var compose = function(){
			var dial = $select.val(), value = $.trim($input.val());
			if (!value) return;
			if (value.charAt(0) === '+') return;
			$input.val(dial ? dial + ' ' + formatNational(dial, value) : value);
		};
		$input.closest('form').on('submit checkout_place_order', compose);
		$(document.body).on('checkout_error', function(){ apply($input.val()); });
	}

	/* ---------- Cégként vásárolok ---------- */
	function companyToggle(){
		var $box = $('#vk_is_company');
		if (!$box.length) return;
		var on = $box.is(':checked');
		$('#billing_company_field, #billing_tax_number_field').toggle(on);
		if (!on) $('#billing_tax_number_field .vk-tax-status').hide();
	}

	$(function(){
		$('input#billing_phone, input#shipping_phone, .woocommerce-address-fields input[type=tel]').each(function(){ setupPhone(this); });
		companyToggle();
		$(document.body).on('change', '#vk_is_company', companyToggle);
		$.each(['billing', 'shipping'], function(i, prefix){
			var timer;
			$(document.body).on('input change', '#' + prefix + '_postcode', function(){
				clearTimeout(timer); timer = setTimeout(function(){ onPostcode(prefix); }, 150);
			});
			$(document.body).on('change', '#' + prefix + '_country', function(){ onPostcode(prefix); });
			if ($('#' + prefix + '_postcode').val()) onPostcode(prefix);
		});
		var taxTimer;
		$(document.body).on('input', '#billing_tax_number', function(){
			clearTimeout(taxTimer); taxTimer = setTimeout(onTaxNumber, 500);
		});
		$(document.body).on('change blur', '#billing_tax_number', onTaxNumber);
	});
})(jQuery);
