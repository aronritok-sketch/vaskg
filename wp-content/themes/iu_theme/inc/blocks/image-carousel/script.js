(function($){

	$('.iu-image-carousel .iu-image-carousel-slider').each(function(){
		const data = $(this).data('slick');
		data.appendArrows = $(this).next('.iu-image-carousel-arrows');
		data.prevArrow = '<a class="iu-button iu-button-default slick-prev"><span class="dashicons dashicons-arrow-left-alt2"></span></a>';
		data.nextArrow = '<a class="iu-button iu-button-default slick-next"><span class="dashicons dashicons-arrow-right-alt2"></span></a>';
		data.responsive = [
			{
		      	breakpoint: 991.8,
		     	settings: {
			        slidesToShow: 1,
			        slidesToScroll: 1
		      	}
		    }
		];
		$(this).slick(data);

		if (data.infinite) return;

		$(this).parent().on('click', '.slick-arrow', function(e){
			e.preventDefault();

			if (!$(this).hasClass('slick-disabled')) return;

			const $slider = $(this).parents('.iu-image-carousel').find('.iu-image-carousel-slider');
			const last = $slider.find('.slick-slide').length-parseInt($slider.data('slick').slidesToShow);
			
			if ($(this).hasClass('slick-prev')) $slider.slick('slickGoTo', last);
			else $slider.slick('slickGoTo', 0);
		});
	});

})(jQuery);