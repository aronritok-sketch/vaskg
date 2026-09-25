(function($){

	$('.iu-form .iu-form-text').on('validate',function(){
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
				case 'email': {
					if (!String($this.find('[name]').val()).toLowerCase().match(/^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|.(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/)){
						$this.addClass('iu-form-field-error');
						$this.find('.iu-form-field-error-message').text(message);
						return false;
					} else {
						$this.removeClass('iu-form-field-error');
						$this.find('.iu-form-field-error-message').empty();
					}
				}
			}

			return true;
		});
	});

	$('.iu-form .iu-form-text[data-validate] input').on('blur',function(){
		$(this).closest('.iu-form-field').trigger('validate');
	});

})(jQuery);