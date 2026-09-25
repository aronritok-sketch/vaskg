(function($){

	$('.iu-form .iu-form-accept').on('validate',function(){
		const [method, message] = $(this).data('validate').split('|');
		if (!$(this).find('[name]').is(':checked')){
			$(this).addClass('iu-form-field-error');
			$(this).find('.iu-form-field-error-message').text(message);
		} else {
			$(this).removeClass('iu-form-field-error');
			$(this).find('.iu-form-field-error-message').empty();
		}
	});

	$('.iu-form .iu-form-accept[data-validate] [name]').on('change',function(){
		$(this).closest('.iu-form-field').trigger('validate');
	});

})(jQuery);