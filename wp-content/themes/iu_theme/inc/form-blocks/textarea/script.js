(function($){

	$('.iu-form .iu-form-textare').on('validate',function(){
		const $this = $(this);
		const methods = $(this).data('validate') ? $(this).data('validate').split("\n") : [];

		methods.every(function(m){
			const [method, message] = m.split('|');

			switch(method){
				case 'required': {
					if ($this.find('[name]').val() === ''){
						$this.addClass('iu-form-field-error');
						$this.find('.iu-form-field-error-message').text(message);
						return false;
					} else {
						$this.removeClass('iu-form-field-error');
						$this.find('.iu-form-field-error-message').empty();
					}
				} break;
			}

			return true;
		});
	});

	$('.iu-form .iu-form-textare[data-validate] textarea').on('blur',function(){
		$(this).closest('.iu-form-field').trigger('validate');
	});

})(jQuery);