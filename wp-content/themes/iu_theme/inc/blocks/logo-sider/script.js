(function($){

	$('.iu-logo-slider').each(function(){

		const resize = function($el){
			const $slider = $el.find('.iu-logo-slider-slider');

			if (!$el.hasClass('initialized')){
				$slider.clone().appendTo($el.find('.iu-logo-slider-sliders'));
			}

			const count = $el.find('img').length;

			$el.find('.iu-logo-slider-sliders').width( window.matchMedia("(width <= 998.2px)").matches ?  $el.width()* .8 * count : $el.width() / 6 * count);
			$el.addClass('initialized');
		}

		$(window).on('resize', resize($(this)));
		resize($(this));

	});

})(jQuery);