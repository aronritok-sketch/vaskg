(function($){

	/*
	 * Fejléc kereső (inc/search.php): a keretrendszer legördülője helyett teljes szélességű
	 * találati panel a fejléc alatt, mint a megamenü. Enter / nagyító: keresés oldal.
	 */

	const settings = window.vasskisgepSearch || {};
	let panel = null;
	let request = null;
	let timer = null;
	let lastTerm = '';

	const header = function(){ return document.querySelector('header'); };

	const build = function(){
		if (panel) return panel;
		panel = $('<div class="vk-search-panel" role="region" aria-label="Keresési találatok" hidden>'
			+ '<button type="button" class="vk-search-close" aria-label="Bezárás"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M5 5l14 14M19 5 5 19"/></svg></button>'
			+ '<div class="vk-search-body"></div></div>').appendTo(header() || document.body);
		panel.on('click', '.vk-search-close', close);
		return panel;
	};

	const position = function(){
		if (!panel) return;
		const bar = document.querySelector('header .header-menu') || header();
		if (!bar) return;
		const row = bar.querySelector('.iu-row') || bar;
		const barRect = bar.getBoundingClientRect();
		const rowRect = row.getBoundingClientRect();
		const mobile = window.matchMedia('(max-width: 991.8px)').matches;
		panel.css({
			top: Math.round(barRect.bottom) + 'px',
			left: mobile ? 0 : Math.round(rowRect.left) + 'px',
			width: mobile ? '100%' : Math.round(rowRect.width) + 'px'
		});
	};

	const open = function(){
		build();
		position();
		panel.prop('hidden', false);
		$('body').addClass('vk-search-open');
	};
	const close = function(){
		if (panel) panel.prop('hidden', true);
		$('body').removeClass('vk-search-open');
	};

	const go = function(term){
		term = (term || '').trim();
		if (term === '') return;
		window.location.href = (settings.search_url || '/') + '?s=' + encodeURIComponent(term);
	};

	const search = function($input){
		const term = $input.val().trim();
		const $wrap = $input.closest('.iu-woocommerce-search');
		if (term.length < (settings.min || 2)){
			if (request) request.abort();
			$wrap.removeClass('loading');
			lastTerm = '';
			close();
			return;
		}
		if (term === lastTerm && panel && !panel.prop('hidden')) return;
		lastTerm = term;
		if (request) request.abort();
		$wrap.addClass('loading');
		open();
		panel.addClass('vk-search-loading');
		request = $.getJSON(settings.ajax_url, { action: 'vasskisgep_search', s: term }).done(function(response){
			panel.find('.vk-search-body').html(response.data.html);
			panel.removeClass('vk-search-loading');
			$wrap.removeClass('loading');
			position();
		}).fail(function(xhr, status){
			if (status !== 'abort'){ panel.removeClass('vk-search-loading'); $wrap.removeClass('loading'); }
		});
	};

	$(window).on('load', function(){
		const $inputs = $('.iu-woocommerce-search-input input');
		// A keretrendszer saját találati legördülője helyett a panel.
		$inputs.off('input');
		$inputs.attr({ 'aria-label': 'Keresés a termékek között', autocomplete: 'off', enterkeyhint: 'search' });

		$inputs.on('input', function(){
			const $input = $(this);
			clearTimeout(timer);
			timer = setTimeout(function(){ search($input); }, 250);
		});
		$inputs.on('keydown', function(e){
			if (e.key === 'Enter'){ e.preventDefault(); go(this.value); }
			if (e.key === 'Escape'){ close(); }
		});
		$inputs.on('focus', function(){
			if (this.value.trim().length >= (settings.min || 2) && lastTerm === this.value.trim() && panel) open();
		});
		$('.iu-woocommerce-search-input .iu-button').on('click', function(e){
			e.preventDefault();
			go($(this).closest('.iu-woocommerce-search-input').find('input').val());
		});
	});

	$(document).on('keydown', function(e){ if (e.key === 'Escape') close(); });
	$(document).on('click', function(e){
		if (!panel || panel.prop('hidden')) return;
		if ($(e.target).closest('.vk-search-panel, .iu-woocommerce-search').length) return;
		close();
	});
	$(window).on('resize scroll', function(){ if (panel && !panel.prop('hidden')) position(); });

})(jQuery);
