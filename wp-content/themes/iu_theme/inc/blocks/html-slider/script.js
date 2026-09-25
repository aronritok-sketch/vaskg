(function($){

	$('.iu-html-slider .iu-html-slider-slider').each(function(){
		const data = $(this).data('slick');
		data.appendArrows = $(this).next('.iu-html-slider-arrows');
		data.prevArrow = '<a class="slick-prev"><span class="dashicons dashicons-arrow-left-alt"></span></a>';
		data.nextArrow = '<a class="slick-next"><span class="dashicons dashicons-arrow-right-alt"></span></a>';
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

			const $slider = $(this).parents('.iu-html-slider').find('.iu-html-slider-slider');
			const last = $slider.find('.slick-slide').length-parseInt($slider.data('slick').slidesToShow);
			
			if ($(this).hasClass('slick-prev')) $slider.slick('slickGoTo', last);
			else $slider.slick('slickGoTo', 0);
		});
	});

})(jQuery);