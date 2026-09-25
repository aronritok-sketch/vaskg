(function($){

	$(document).ready(function(){

		$('.iu-slider .iu-slider-slider').each(function(){
			$(this).after('<div class="iu-slider-arrows"></div>');

			const data = $(this).data('slick') || {};
			data.fade = true;
			data.infinite = true;
			data.pauseOnFocus = false;
			data.pauseOnHover = true;
			data.slidesToShow = 1;
			data.slidesToScroll = 1;
			data.prevArrow = '<a class="slick-prev"><span class="dashicons dashicons-arrow-left-alt2"></span></a>';
			data.nextArrow = '<a class="slick-next"><span class="dashicons dashicons-arrow-right-alt2"></span></a>';
			data.appendArrows = $(this).next('.iu-slider-arrows');
			$(this).slick(data);
		});

	});

})(jQuery);