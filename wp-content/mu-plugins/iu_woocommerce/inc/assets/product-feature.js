(function($){

	$(document).ready(function(){

		$('.iu-woocommerce-product-feature-slider').each(function(){
			const data = {
				slidesToShow: 1,
				slidesToScroll: 1,
				infinite: true,
				autoplay: true,
				fade: true,
				pauseOnFocus: false,
				pauseOnHover: true,
				arrows: true,
				appendArrows: $(this).next('.iu-woocommerce-product-feature-slider-arrows'),
				prevArrow: '<a class="slick-prev"><span class="dashicons dashicons-arrow-left-alt2"></span></a>',
				nextArrow: '<a class="slick-next"><span class="dashicons dashicons-arrow-right-alt2"></span></a>'
			};
			$(this).slick(data);

			$(this).on('beforeChange',function(e, slick, current, next){
				$(this).closest('.iu-woocommerce-product-feature').find('.iu-woocommerce-product-feature-image img').eq(next).addClass('iu-woocommerce-product-feature-active').siblings().removeClass('iu-woocommerce-product-feature-active');
			});
		});

	});

})(jQuery)