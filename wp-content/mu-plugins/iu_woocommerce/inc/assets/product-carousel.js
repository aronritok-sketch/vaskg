(function($){

	$(document).ready(function(){

		$('.iu-woocommerce-product-carousel .iu-woocommerce-product-carousel-slider').each(function(){
			const data = $(this).data('slick');
			data.appendArrows = $(this).next('.iu-woocommerce-product-carousel-arrows');
			data.prevArrow = '<a class="slick-prev"><span class="dashicons dashicons-arrow-left-alt2"></span></a>';
			data.nextArrow = '<a class="slick-next"><span class="dashicons dashicons-arrow-right-alt2"></span></a>';
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

				const $slider = $(this).parents('.iu-woocommerce-product-carousel').find('.iu-woocommerce-product-carousel-slider');
				if ($(this).hasClass('slick-next')){
					$slider.slick('slickGoTo', 0);
					return;
				}

				const last = $slider.find('.slick-slide').length-($slider.find('.slick-slide').length%parseInt($slider.data('slick').slidesToShow));
				$slider.slick('slickGoTo', last);
			});
		});

	});

})(jQuery)