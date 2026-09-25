jQuery(document).ready(function($){

	document.querySelectorAll('.nav-tab-wrapper a.nav-tab').forEach(function(tab){
		tab.addEventListener('click', function(event){console.log(event);
			if (event.target.classList.contains('nav-tab-active')) return;

			document.querySelector('.nav-tab-wrapper a.nav-tab-active').classList.toggle('nav-tab-active');
			event.target.classList.toggle('nav-tab-active');

			const index = Array.prototype.indexOf.call(event.target.parentNode.children, event.target);
			document.querySelector('.tab-contents:not(.hidden)').classList.toggle('hidden');
			document.querySelectorAll('.tab-contents')[index].classList.toggle('hidden');
		});
	});

	const disableForm = function(form){
		form.style.opacity = 0.7;
		form.style.pointerEvents = 'none';
		form.querySelector('[type=submit]').disabled = true;
	};

	const enabelForm = function(form){
		form.style = null;
		form.querySelector('[type=submit]').disabled = false;
	}

	const showNotice = function(form, text){
		let notice = form.parentElement.querySelector('.notice');
		notice.querySelector('strong').innerHTML = text;
		notice.style.display = 'block';
	}

	const showErrors = function(form, errors){
		const tabs = form.querySelector('.nav-tab-wrapper');
		Object.entries(errors).forEach(function([field, message]){
			let fieldElement = form.querySelector('[name="'+field+'"]');
			let error = fieldElement.closest('td').querySelector('.error');
			error.innerHTML = message;
			error.style.display = 'block';

			if (tabs) {
				const tabContent = fieldElement.closest('.tab-contents');
				const index = Array.prototype.indexOf.call(tabContent.parentNode.querySelectorAll('.tab-contents'), tabContent);
				
				form.querySelectorAll('.nav-tab')[index].style.color = '#b32d2e';
			}
		});
	}

	const removeErrors = function(form){
		form.querySelectorAll('.nav-tab').forEach(function(tab){
			tab.style.color = null;
		});
		form.querySelectorAll('td .error').forEach(function(error){
			error.style.display = 'none';
		});
	}

	document.querySelector('.notice button').addEventListener('click', function(event){
		event.target.closest('.notice').style.display = 'none';
	});

	document.querySelectorAll('form.iu-settings-form').forEach(function(form){
		form.addEventListener('submit', function(event){
			event.preventDefault();

			disableForm(form);
			form.parentElement.querySelector('.notice').style.display = 'none';

			const formData = new FormData(form);
			fetch(window.location.href, { method: 'POST', body: formData })
				.then( (r) => r.json() )
				.then( (json) => {
					removeErrors(form);
					enabelForm(form);
					if (!!json.success){
						showNotice(form, json.success);
						return;
					}

					showErrors(form, json.errors);
				});

		});
	});

});