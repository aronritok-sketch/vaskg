(function($){

	$('.iu-accordion').each(function(){
		$(this).find('.iu-accordion-item:first-child').addClass('iu-accordion-item-open').find('.iu-accordion-item-body').show();

		$(this).on('click', ':not(.iu-accordion-item-open) .iu-accordion-item-head', function(){
			$(this).closest('.iu-accordion').find('.iu-accordion-item-open .iu-accordion-item-body').slideToggle();
			$(this).closest('.iu-accordion-item').addClass('iu-accordion-item-open').siblings().removeClass('iu-accordion-item-open');
			$(this).closest('.iu-accordion-item').find('.iu-accordion-item-body').slideToggle();
		});
	});

})(jQuery);