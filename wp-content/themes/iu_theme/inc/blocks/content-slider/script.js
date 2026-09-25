(function($){

	$(document).ready(function(){

		$('.iu-content-slider .iu-content-slider-slider').each(function(){
			$(this).after('<div class="iu-content-slider-arrows"></div>');

			const data = $(this).data('slick') || {};
			data.pauseOnFocus = false;
			data.pauseOnHover = true;
			data.prevArrow = '<a class="slick-prev"><span class="dashicons dashicons-arrow-left-alt2"></span></a>';
			data.nextArrow = '<a class="slick-next"><span class="dashicons dashicons-arrow-right-alt2"></span></a>';
			data.appendArrows = $(this).next('.iu-content-slider-arrows');
			$(this).slick(data);
		});

	});

})(jQuery);