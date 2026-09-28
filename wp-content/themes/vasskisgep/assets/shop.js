(function($){

	/* ---------- Kedvencek és összehasonlítás (sütiben tárolt listák) ---------- */

	const cookies = { wishlist: 'vk_wishlist', compare: 'vk_compare' };
	const settings = window.vasskisgep || {};

	const read = function(list){
		const match = document.cookie.match(new RegExp('(?:^|; )'+cookies[list]+'=([^;]*)'));
		if (!match) return [];
		return decodeURIComponent(match[1]).split(',').map(Number).filter(Boolean);
	};
	const write = function(list, ids){
		const path = (settings.cookie_path || '/');
		document.cookie = cookies[list]+'='+encodeURIComponent(ids.join(','))+'; path='+path+'; max-age=31536000; SameSite=Lax';
	};

	const notify = function(message, link){
		let $toast = $('.vk-toast');
		if (!$toast.length) $toast = $('<div class="vk-toast" role="status" aria-live="polite"></div>').appendTo('body');
		$toast.html('<span>'+message+'</span>'+(link ? ' <a href="'+link.url+'">'+link.label+'</a>' : '')).addClass('vk-toast-show');
		clearTimeout($toast.data('timer'));
		$toast.data('timer', setTimeout(function(){ $toast.removeClass('vk-toast-show'); }, 3500));
	};

	const refresh = function(){
		Object.keys(cookies).forEach(function(list){
			const ids = read(list);
			$('.vk-list-toggle[data-list="'+list+'"]').each(function(){
				const active = ids.indexOf(Number(this.dataset.product)) !== -1;
				this.classList.toggle('vk-active', active);
				this.setAttribute('aria-pressed', active ? 'true' : 'false');
			});
			// A fejléc ikonjai (iu/icon-text) a lista oldalára mutatnak.
			const url = list === 'wishlist' ? settings.wishlist_url : settings.compare_url;
			if (!url) return;
			$('header a.iu-icon-text').filter(function(){ return this.href === url; }).each(function(){
				const $icon = $(this).find('.iu-icon').first();
				const $target = $icon.length ? $icon : $(this);
				let $badge = $target.find('.vk-count');
				if (!$badge.length) $badge = $('<span class="vk-count"></span>').appendTo($target.addClass('vk-has-count'));
				$badge.text(ids.length || '').toggleClass('vk-count-empty', !ids.length);
			});
		});
	};

	const sync = function(){
		if (!document.body.classList.contains('logged-in') || !settings.lists_nonce) return;
		$.post(settings.ajax_url, {
			action: 'vasskisgep_list_sync',
			security: settings.lists_nonce,
			wishlist: read('wishlist').join(','),
			compare: read('compare').join(',')
		});
	};

	$(document).on('click', '.vk-list-toggle', function(e){
		e.preventDefault();
		e.stopPropagation();

		const list = this.dataset.list;
		const id = Number(this.dataset.product);
		let ids = read(list);
		const index = ids.indexOf(id);

		if (index !== -1){
			ids.splice(index, 1);
			notify(list === 'wishlist' ? 'Eltávolítva a kedvencek közül.' : 'Eltávolítva az összehasonlításból.');
		} else {
			if (list === 'compare' && ids.length >= (settings.compare_limit || 4)){
				notify('Legfeljebb '+(settings.compare_limit || 4)+' terméket hasonlíthat össze.', { url: settings.compare_url, label: 'Összehasonlítás megnyitása' });
				return;
			}
			ids.push(id);
			notify(
				list === 'wishlist' ? 'Hozzáadva a kedvencekhez.' : 'Hozzáadva az összehasonlításhoz.',
				list === 'wishlist' ? { url: settings.wishlist_url, label: 'Kedvencek' } : { url: settings.compare_url, label: 'Összehasonlítás' }
			);
		}
		write(list, ids);
		refresh();
		sync();

		// A lista oldalán az eltávolított elem azonnal eltűnik.
		const $page = $(this).closest('[data-list-page="'+list+'"]');
		if ($page.length && index !== -1) window.location.reload();
	});

	$(refresh);
	$(document).on('vk:products-updated', refresh);


	/* ---------- Termékoldal: fülek közötti ugrás, mennyiség ---------- */

	$(document).on('click', 'a[href="#vk-specs"]', function(e){
		const $tabs = $('.vk-product-tabs .iu-tabs-tabs a');
		if (!$tabs.length) return;
		e.preventDefault();
		$tabs.eq(1).trigger('click');
		$('html, body').animate({ scrollTop: $('.vk-product-tabs').offset().top - 120 }, 300);
	});

	/* ---------- Térkép: csak kattintásra töltődik be ---------- */

	$(document).on('click', '.vk-map-load', function(){
		const $map = $(this).closest('.vk-map');
		$('<iframe loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>')
			.attr({ src: $map.data('src'), title: $map.data('title') })
			.appendTo($map.empty().addClass('vk-map-loaded'));
	});


	/* ---------- Kezdőlap: márka-hero mobilon (érintésre nyílik) ---------- */

	$(document).on('click', '.vasskisgep-pager-hero-slide:not(.open)', function(e){
		if (!window.matchMedia('(max-width: 991.8px)').matches) return;
		e.preventDefault();
		$(this).addClass('open').siblings().removeClass('open');
	});


	/* ---------- Kezdőlap: szezonális fülek ---------- */

	$(document).on('click', '.vk-season-tabs button', function(){
		const $wrap = $(this).closest('.vk-season-products');
		const season = this.dataset.season;
		$wrap.find('.vk-season-tabs button').removeClass('vk-active').attr('aria-selected', 'false');
		$(this).addClass('vk-active').attr('aria-selected', 'true');
		$wrap.find('.vk-season-pane').removeClass('vk-active').filter('[data-season="'+season+'"]').addClass('vk-active')
			.find('.slick-initialized').slick('setPosition');
	});

})(jQuery);

/* ---------- Termékoldal (design: Termékoldal) ---------- */
(function($){

	// Mennyiségválasztó: a WooCommerce AJAX kosárba rakása a gomb data-quantity értékét használja.
	const syncQty = function($form){
		const $input = $form.find('input.qty');
		const min = parseInt($input.attr('min'), 10) || 1;
		const max = parseInt($input.attr('max'), 10) || Infinity;
		let value = parseInt($input.val(), 10);
		if (isNaN(value) || value < min) value = min;
		if (value > max) value = max;
		$input.val(value);
		$form.find('.vk-buy-button').attr('data-quantity', value).data('quantity', value);
	};
	$(document).on('click', '.vk-qty-btn', function(){
		const $form = $(this).closest('.vk-buy');
		const $input = $form.find('input.qty');
		$input.val((parseInt($input.val(), 10) || 0) + parseInt(this.dataset.step, 10));
		syncQty($form);
	});
	$(document).on('change input', '.vk-buy input.qty', function(){
		syncQty($(this).closest('.vk-buy'));
	});

	// Kapcsolódó termékek oldalsáv: fel / le léptetés.
	const xsUpdate = function($box){
		const list = $box.find('.vk-xs-list')[0];
		if (!list) return;
		$box.find('.vk-xs-prev').prop('disabled', list.scrollTop <= 0);
		$box.find('.vk-xs-next').prop('disabled', list.scrollTop + list.clientHeight >= list.scrollHeight - 2);
	};
	$(document).on('click', '.vk-xs-prev, .vk-xs-next', function(){
		const $box = $(this).closest('.vk-cross-sells');
		const list = $box.find('.vk-xs-list')[0];
		const step = ($box.find('.vk-xs-item').outerHeight(true) || 80) + 8;
		list.scrollTop += $(this).hasClass('vk-xs-next') ? step : -step;
		setTimeout(function(){ xsUpdate($box); }, 350);
	});

	// Csillagokra kattintva a Vélemények fül nyílik meg.
	const openTab = function(title){
		const $tab = $('.vk-product-tabs .iu-tabs-tabs a').filter(function(){ return $(this).text().trim() === title; }).first();
		if (!$tab.length) return false;
		$tab.trigger('click');
		$('html, body').animate({ scrollTop: $tab.offset().top - 120 }, 300);
		return true;
	};
	$(document).on('click', '[data-vk-tab="reviews"]', function(e){
		if (openTab('Vélemények')) e.preventDefault();
	});

	// Cofidis kalkulátor.
	const format = function(value){
		return Math.round(value).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' Ft';
	};
	const cofidis = function($box){
		const config = $box.data('config');
		const months = parseInt($box.find('input[name="vk-cof-term"]:checked').val(), 10) || 12;
		const $down = $box.find('#vk-cof-down');
		let down = Math.max(0, Math.min(config.price, parseInt($down.val(), 10) || 0));
		let loan = config.price - down;
		if (config.max > 0 && loan > config.max){
			loan = config.max;
			down = config.price - loan;
		}
		const rate = config.interest !== null && config.interest !== undefined
			? config.interest / 100 / 12
			: Math.pow(1 + config.thm / 100, 1 / 12) - 1;
		let monthly = 0;
		if (loan > 0) monthly = rate > 0 ? Math.ceil(loan * rate / (1 - Math.pow(1 + rate, -months))) : Math.ceil(loan / months);
		const tooSmall = config.min > 0 && loan < config.min;

		$box.find('.vk-cof-loan').text(format(loan));
		$box.find('.vk-cof-months').text(months + ' hó');
		$box.find('.vk-cof-monthly').text(tooSmall ? '–' : format(monthly));
		$box.find('.vk-cof-total').text(tooSmall ? '–' : format(monthly * months + down));
	};
	$(document).on('change input', '.vk-cofidis input', function(){
		cofidis($(this).closest('.vk-cofidis'));
	});

	$(function(){
		$('.vk-buy').each(function(){ syncQty($(this)); });
		$('.vk-cross-sells').each(function(){ xsUpdate($(this)); });
		$('.vk-cofidis').each(function(){ cofidis($(this)); });
		if (window.location.hash === '#vk-reviews' || window.location.hash.indexOf('#comment') === 0 || window.location.hash === '#reviews') openTab('Vélemények');
	});

})(jQuery);

/* ---------- Márkarács lapozása (Rólunk): 9 logó oldalanként, pöttyökkel ---------- */
(function($){
	const PER_PAGE = 9;
	$('.vk-brand-grid').each(function(){
		const $grid = $(this);
		const $items = $grid.children('.iu-image');
		const pages = Math.ceil($items.length / PER_PAGE);
		if (pages < 2) return;
		const $dots = $('<div class="vk-brand-dots" role="tablist"></div>');
		const show = function(page){
			$items.each(function(i){ $(this).toggleClass('vk-page-hidden', Math.floor(i / PER_PAGE) !== page); });
			$dots.children().removeClass('vk-active').attr('aria-selected', 'false').eq(page).addClass('vk-active').attr('aria-selected', 'true');
		};
		for (let i = 0; i < pages; i++){
			$('<button type="button" role="tab"></button>').attr('aria-label', (i + 1) + '. oldal').on('click', function(){ show(i); }).appendTo($dots);
		}
		$grid.append($dots);
		show(0);
	});
})(jQuery);
