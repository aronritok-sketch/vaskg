jQuery(document).ready(function($){

	$('.vasskisgep-pager-hero-slide').on('transitionstart',function(e){
		if (e.target != e.currentTarget) return;
		$(this).addClass('transitioning');
	});

	$('.vasskisgep-pager-hero-slide').on('transitionend',function(e){
		if (e.target != e.currentTarget) return;

		$(this).removeClass('transitioning');
		$(this).parent().find('.vasskisgep-pager-hero-slide').filter(function(){ return $(this).is(':hover') }).trigger('mousemove');
	});

	$('.vasskisgep-pager-hero-slide').on('mousemove', function(){
		if ($(this).siblings('.transitioning').length > 0) return;
		
		const self = this;
		setTimeout(function(){
			if ($(self).is(':hover')) $(self).addClass('open').siblings().removeClass('open');
		}, 66);
	});

	// Az egész kép kattintható: a nyitott sáv a márka termékeire visz; érintőképernyőn az első
	// koppintás kinyitja a sávot, a második visz tovább.
	$('.vasskisgep-pager-hero-slide').on('click', function(e){
		if ($(e.target).closest('a').length) return;
		const $slide = $(this);
		if (!$slide.hasClass('open')){
			$slide.addClass('open').siblings().removeClass('open');
			return;
		}
		const href = $slide.find('.vasskisgep-pager-hero-slide-open a[href]').first().attr('href');
		if (href) window.location.href = href;
	});

});
