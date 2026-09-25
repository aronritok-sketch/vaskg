(function($){

	$('.iu-form').each(function(){
		$(this).find('.iu-form-step').first().addClass('active').find('.iu-form-step-prev').css('visibility','hidden');
		$(this).find('.iu-form-step').last().find('.iu-form-step-next').hide();

		$(this).find('.iu-form-step').each(function(){

			$(this).find('.iu-form-step-prev').on('click',function(){
				$(this).closest('.iu-form-step').prev('.iu-form-step').trigger('iu-form-step-active');
				$(this).closest('.iu-form').find('.iu-form-steps').trigger('iu-form-steps-prev');
			});

			$(this).find('.iu-form-step-next').on('click',function(){
				$(this).closest('.iu-form-step').find('.iu-form-field[data-validate]').trigger('validate');
				if ($(this).closest('.iu-form-step').find('.iu-form-field-error').length === 0){
					$(this).closest('.iu-form-step').next('.iu-form-step').trigger('iu-form-step-active');
					$(this).closest('.iu-form').find('.iu-form-steps').trigger('iu-form-steps-next');
				}
			});

			$(this).on('iu-form-step-active',function(){
				$(this).addClass('active').siblings().removeClass('active');
				if ($(this).is($(this).closest('.iu-form').find('.iu-form-step').last())){
					$(this).closest('.iu-form').find('.iu-form-foot').show();
				} else {
					$(this).closest('.iu-form').find('.iu-form-foot').hide();
				}

				$(this).closest('.iu-form')[0].scrollIntoView({ behavior: 'smooth' });
			});
		});
	});

})(jQuery);