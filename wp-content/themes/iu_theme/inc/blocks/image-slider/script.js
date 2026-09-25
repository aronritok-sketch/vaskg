(function($){

	$('.iu-image-slider .iu-image-slider-slider').each(function(){
		const data = $(this).data('slick');
		data.infinite = true;
		data.autoplay = true;
		data.pauseOnFocus = false;
		data.pauseOnHover = true;
		data.slidesToShow = 1;
		data.slidesToScroll = 1;
		data.prevArrow = '<a class="slick-prev"><span class="dashicons dashicons-arrow-left-alt"></span></a>';
		data.nextArrow = '<a class="slick-next"><span class="dashicons dashicons-arrow-right-alt"></span></a>';
		data.appendArrows = '.iu-image-slider-arrows';
		$(this).after('<div class="iu-image-slider-arrows"></div>');
		$(this).slick(data);
	});

})(jQuery);