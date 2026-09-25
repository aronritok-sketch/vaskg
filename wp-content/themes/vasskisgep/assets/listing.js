(function($){

	/*
	 * Terméklista szűrők (vasskisgep/product-listing blokk).
	 * A kiválasztott értékek URL-paraméterek; a lista frissítése ugyanarra az URL-re
	 * küldött vk_listing=results|count kéréssel történik.
	 */

	const params = function($form, extra){
		const values = {};
		$form.find('input[type=checkbox]:checked, input[type=radio]:checked').each(function(){
			if (this.value === '') return;
			values[this.name] = values[this.name] ? values[this.name] + ',' + this.value : this.value;
		});
		$form.find('input[type=number]').each(function(){
			if (this.value !== '') values[this.name] = this.value;
		});
		return Object.assign(values, extra || {});
	};

	const url = function(query){
		const qs = new URLSearchParams(query).toString();
		return window.location.pathname + (qs ? '?' + qs : '');
	};

	let pending = null;
	const request = function(query, mode){
		if (pending) pending.abort();
		pending = $.ajax({ url: window.location.pathname, data: Object.assign({}, query, { vk_listing: mode }), dataType: 'json', cache: false });
		return pending;
	};

	const updateCounts = function($form){
		$form.find('.vk-dd').each(function(){
			const $dd = $(this);
			if ($dd.hasClass('vk-dd-sort')) return;
			let n = $dd.find('input[type=checkbox]:checked').length;
			if ($dd.hasClass('vk-dd-price')) n = $dd.find('input[type=number]').filter(function(){ return this.value !== ''; }).length ? 1 : 0;
			$dd.find('.vk-dd-count').text(n ? ' (' + n + ')' : '');
			$dd.toggleClass('vk-dd-selected', n > 0);
		});
	};

	const updateMore = function($form, total){
		const shown = $form.find('.vk-results li.product').length;
		$form.find('.vk-shown').text(shown);
		$form.find('.vk-total').text(total);
		$form.find('.vk-more').prop('hidden', shown >= total);
		$form.find('.vk-dd-total').text(total);
		$form.attr('data-total', total);
	};

	const refreshed = function(){
		$(document).trigger('vk:products-updated');
	};

	const apply = function($form){
		const query = params($form);
		const $results = $form.find('.vk-results').addClass('vk-loading');
		history.replaceState({}, '', url(query));
		closeAll();
		updateCounts($form);
		request(query, 'results').done(function(response){
			$results.html(response.data.html).removeClass('vk-loading');
			updateMore($form, response.data.total);
			refreshed();
		}).fail(function(xhr, status){
			if (status !== 'abort') $results.removeClass('vk-loading');
		});
	};

	const preview = function($form, $dd){
		$dd.addClass('vk-counting');
		request(params($form), 'count').done(function(response){
			$dd.removeClass('vk-counting').find('.vk-dd-total').text(response.data.total);
		});
	};

	const closeAll = function(except){
		$('.vk-dd.vk-open').not(except || []).removeClass('vk-open').find('.vk-dd-toggle').attr('aria-expanded', 'false');
	};

	$(document).on('click', '.vk-dd-toggle', function(e){
		e.preventDefault();
		const $dd = $(this).closest('.vk-dd');
		closeAll($dd);
		$dd.toggleClass('vk-open');
		$(this).attr('aria-expanded', $dd.hasClass('vk-open') ? 'true' : 'false');
	});

	$(document).on('click', function(e){
		if (!$(e.target).closest('.vk-dd').length) closeAll();
	});
	$(document).on('keydown', function(e){
		if (e.key === 'Escape') closeAll();
	});

	// Opció választás: darabszám előnézet (a rendezés azonnal alkalmazódik).
	$(document).on('change', '.vk-listing .vk-dd input', function(){
		const $form = $(this).closest('.vk-listing');
		const $dd = $(this).closest('.vk-dd');
		if (this.name === 'orderby'){
			apply($form);
			return;
		}
		preview($form, $dd);
	});
	$(document).on('input', '.vk-listing .vk-dd-price input', function(){
		const $form = $(this).closest('.vk-listing');
		clearTimeout($form.data('timer'));
		$form.data('timer', setTimeout(() => preview($form, $(this).closest('.vk-dd')), 400));
	});

	$(document).on('click', '.vk-dd-apply', function(){
		apply($(this).closest('.vk-listing'));
	});

	$(document).on('click', '.vk-dd-clear', function(){
		const $dd = $(this).closest('.vk-dd');
		$dd.find('input[type=checkbox]').prop('checked', false);
		$dd.find('input[type=number]').val('');
		apply($(this).closest('.vk-listing'));
	});

	$(document).on('keydown', '.vk-listing input[type=number]', function(e){
		if (e.key === 'Enter'){
			e.preventDefault();
			apply($(this).closest('.vk-listing'));
		}
	});

	// Rács / lista nézet (a keretrendszer sütijét használja).
	$(document).on('click', '.vk-view-btn', function(){
		const view = this.dataset.view;
		document.cookie = 'iu-woocommerce-list-view=' + view + '; path=/; max-age=31536000; SameSite=Lax';
		$(this).addClass('vk-active').siblings().removeClass('vk-active');
		apply($(this).closest('.vk-listing'));
	});

	// Továbbiak mutatása: a következő oldal termékei a lista végére.
	$(document).on('click', '.vk-more-btn', function(){
		const $form = $(this).closest('.vk-listing');
		const $btn = $(this).prop('disabled', true);
		const page = Math.floor($form.find('.vk-results li.product').length / 12) + 1;
		request(params($form, { 'product-page': page }), 'results').done(function(response){
			const $items = $('<div>').html(response.data.html).find('ul.products > li.product');
			$form.find('.vk-results ul.products').append($items);
			updateMore($form, response.data.total);
			$btn.prop('disabled', false);
			refreshed();
		}).fail(function(){ $btn.prop('disabled', false); });
	});

	// Mobilon a részletes szűrés lenyitható.
	$(document).on('click', '.vk-side-toggle', function(){
		$(this).closest('.vk-filter-side').toggleClass('vk-open');
	});

	$(function(){
		$('.vk-listing').each(function(){ updateCounts($(this)); });
	});

})(jQuery);
