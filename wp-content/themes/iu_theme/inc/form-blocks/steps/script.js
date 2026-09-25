(function($){

	$('.iu-form').each(function(){
		$(this).find('.iu-form-steps').each(function(){

			$(this).on('iu-form-steps-prev', function(){
				$(this).find('.active').removeClass('active').prev().addClass('active');
			});

			$(this).on('iu-form-steps-next', function(){
				$(this).find('.active').removeClass('active').next().addClass('active');
			});

			/*$(this).find('.iu-form-steps-step-index').on('click',function(){
				$(this).closest('.iu-form-steps-step').addClass('active').siblings().removeClass('active');
				$(this).closest('.iu-form').find('.iu-form-step').eq($(this).closest('.iu-form-steps-step').index()).trigger('iu-form-step-active');
			});*/

		});
	});

})(jQuery);