jQuery(document).ready(function($){

	// Asztali nézetben (egérrel) a sáv rámutatásra nyílik; mobilon (érintés) lapozó: nyilak, húzás és
	// márkaválasztó csempék fix sorrendben (ügyfélkérés, 2026-10: érintéskor az átrendeződő csempék miatt
	// rossz márka nyílt meg).
	const desktop = window.matchMedia('(min-width: 992px) and (hover: hover)');

	$('.vasskisgep-pager-hero-slide').on('transitionstart',function(e){
		if (e.target != e.currentTarget) return;
		$(this).addClass('transitioning');
	});

	$('.vasskisgep-pager-hero-slide').on('transitionend',function(e){
		if (e.target != e.currentTarget) return;

		$(this).removeClass('transitioning');
		if (!desktop.matches) return;
		$(this).parent().find('.vasskisgep-pager-hero-slide').filter(function(){ return $(this).is(':hover') }).trigger('mousemove');
	});

	$('.vasskisgep-pager-hero-slide').on('mousemove', function(){
		if (!desktop.matches) return;
		if ($(this).siblings('.transitioning').length > 0) return;

		const self = this;
		setTimeout(function(){
			if ($(self).is(':hover')) $(self).addClass('open').siblings().removeClass('open');
		}, 66);
	});

	// Az egész kép kattintható: a nyitott sáv a márka termékeire visz; asztali érintőképernyőn az első
	// koppintás kinyitja a sávot, a második visz tovább.
	$('.vasskisgep-pager-hero-slide').on('click', function(e){
		if ($(e.target).closest('a, .vk-hero-arrow').length) return;
		const $slide = $(this);
		if (!$slide.hasClass('open')){
			$slide.addClass('open').siblings('.vasskisgep-pager-hero-slide').removeClass('open');
			return;
		}
		const href = $slide.find('.vasskisgep-pager-hero-slide-open a[href]').first().attr('href');
		if (href) window.location.href = href;
	});

	/* ---------- Mobil lapozó ---------- */

	$('.vasskisgep-pager-hero').each(function(){
		const $hero = $(this);
		const $slides = $hero.children('.vasskisgep-pager-hero-slide');
		if ($slides.length < 2) return;

		const arrow = function(dir){
			return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + (dir < 0 ? 'm15 6-6 6 6 6' : 'm9 6 6 6-6 6') + '"/></svg>';
		};
		const $prev = $('<button type="button" class="vk-hero-arrow vk-hero-prev" aria-label="Előző márka">' + arrow(-1) + '</button>');
		const $next = $('<button type="button" class="vk-hero-arrow vk-hero-next" aria-label="Következő márka">' + arrow(1) + '</button>');
		const $nav = $('<div class="vk-hero-nav" role="tablist" aria-label="Márkák"></div>');
		$slides.each(function(i){
			const $img = $(this).find('.vasskisgep-pager-hero-slide-closed img').first();
			const label = ($img.attr('alt') || '').replace(/\s*logo\s*$/i, '') || ('Márka ' + (i + 1));
			$('<button type="button" class="vk-hero-nav-item" role="tab"></button>')
				.attr('aria-label', label)
				.append($img.length ? $img.clone().removeAttr('srcset sizes loading') : $('<span></span>').text(label))
				.appendTo($nav);
		});
		$hero.append($prev, $next).after($nav);

		const index = function(){ return Math.max(0, $slides.index($slides.filter('.open'))); };
		const show = function(i){
			i = (i + $slides.length) % $slides.length;
			$slides.removeClass('open').eq(i).addClass('open');
			$nav.children().removeClass('vk-active').attr('aria-selected', 'false').eq(i).addClass('vk-active').attr('aria-selected', 'true');
			// A nyilak a kép függőleges közepén (a nyitott sáv képe).
			const $bg = $slides.eq(i).find('.vasskisgep-pager-hero-slide-bg');
			if ($bg.length && $bg.outerHeight()) $hero[0].style.setProperty('--vk-hero-arrow-top', ($bg.outerHeight() / 2) + 'px');
		};
		show(index());

		$prev.on('click', function(e){ e.stopPropagation(); show(index() - 1); });
		$next.on('click', function(e){ e.stopPropagation(); show(index() + 1); });
		$nav.on('click', '.vk-hero-nav-item', function(){ show($(this).index()); });
		$(window).on('resize', function(){ show(index()); });

		// Húzás: lapozás ujjal (a húzás nem számít koppintásnak).
		let startX = null, startY = null;
		$hero.on('touchstart', function(e){
			const t = e.originalEvent.touches[0];
			startX = t.clientX;
			startY = t.clientY;
		});
		$hero.on('touchend', function(e){
			if (startX === null) return;
			const t = e.originalEvent.changedTouches[0];
			const dx = t.clientX - startX, dy = t.clientY - startY;
			startX = null;
			if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.5){
				e.preventDefault();
				show(index() + (dx < 0 ? 1 : -1));
			}
		});
	});

});
