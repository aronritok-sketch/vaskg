(function($){

	/*const validate = function(field){
		const methods = field.dataset.validate.split(',');
		const input = field.querySelector('[name]');

		for(let m of methods){
			let [method, message] = m.split('|');
			method = method.trim();
			message = message.trim();

			switch(method){
				case 'required': {
					if (input.getAttribute('type') == 'checkbox' && !input.checked) return message;
					if (input.value == '') return message;
				} break;
				case 'email': {
					if (!String(input.value).toLowerCase().match(/^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|.(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/)) return message;	
				} break;
			}
		};

		return false;
	};

	const showError = function(field, message){
		field.classList.add('iu-form-error');
		field.querySelector('.iu-form-error-message').innerHTML = message;
	};
	const hideError = function(field){
		field.classList.remove('iu-form-error');
		field.querySelector('.iu-form-error-message').innerHTML = '';
	}*/

	const showFormError = function(form, message){
		form.classList.add('iu-form-error');
		form.querySelector('.iu-form-error-message').innerHTML = message;
	}
	const hideFormError = function(form){
		form.classList.remove('iu-form-error');
		form.querySelector('.iu-form-error-message').innerHTML = '';
	}

	const forms = document.querySelectorAll('.iu-form');
	forms.forEach(function(form){

		/*let errors = [];
		form.querySelectorAll('.iu-form-field[data-validate]').forEach(function(field){
			field.querySelector('[name]').addEventListener('blur', function(e){
				const field = e.target.closest('.iu-form-field');
				const error = validate(field);
				if (false != error) showError(field, error);
				else hideError(field);
			});
		});
		form.addEventListener('input',function(e){
			if (!e.target.closest('.iu-form-field.iu-form-error')) return;
			hideError(e.target.closest('.iu-form-field'));
		});

		if (!form.querySelector('.iu-form-submit')) return;*/
		$(form).find('.iu-form-submit').on('click', function(e){
			e.preventDefault();

			const form = e.target.closest('form');

			hideFormError(form);
			form.querySelectorAll('.iu-form-field[data-validate]').forEach(function(field){
				/*const error = validate(field);
				if (false != error) showError(field, error);
				else hideError(field);*/
				$(field).trigger('validate');
			});
			
			if (form.querySelectorAll('.iu-form-field-error').length > 0) return false;

			const formData = new FormData(form);
			form.classList.add('iu-form-loading');
			
			fetch(window.location.href, {
				method: 'POST',
            	body: formData
			}).then(function(res){
				return res.json();
			}).then(function(json){
				form.classList.remove('iu-form-loading');

				if (!json.success) {
					for (const [name, message] of Object.entries(json.errors)) {
						$(form.querySelector('[name='+name+']').closest('.iu-form-field')).trigger('error', [message]);
						//showError(form.querySelector('[name='+name+']').closest('.iu-form-field'), message);
					}
					if (json.error){
						showFormError(form, json.error);
					}
				} else {
					form.classList.add('iu-form-success');
					$(form).trigger('success', [json]);

					form.querySelectorAll('.iu-form-field').forEach(function(field){
						$(field).trigger('reset');
					});
					form.reset();
				}
			});
		});

	});

})(jQuery);