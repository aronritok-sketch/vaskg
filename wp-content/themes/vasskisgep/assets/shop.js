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
