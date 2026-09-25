(function($){

	$('.iu-tabs').each(function(){
		$(this).find('.iu-tabs-tabs a:first-child, .iu-tabs-contents .iu-tab:first-child').addClass('iu-tabs-active');

		$(this).find('.iu-tabs-tabs a').on('click', function(){
			$(this).addClass('iu-tabs-active').siblings().removeClass('iu-tabs-active');
			$(this).closest('.iu-tabs-tabs').next('.iu-tabs-contents').find('.iu-tab').eq($(this).index()).addClass('iu-tabs-active').siblings().removeClass('iu-tabs-active');
		});
	});

})(jQuery);