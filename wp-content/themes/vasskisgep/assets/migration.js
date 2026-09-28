(function($){

	/*
	 * Régi webshop átköltöztetés (inc/migration.php): a lépések adagokban futnak, amíg készen nincsenek.
	 * Az oldal nyitva tartása kell hozzá; megszakítás után a gomb újra megnyomható (folytatja).
	 */

	const cfg = window.vkMigration || {};

	const post = function(action, data){
		return $.post(cfg.ajax, $.extend({ action: 'vk_mig_' + action, nonce: cfg.nonce }, data || {}));
	};

	const log = function($card, text){
		const $log = $card.find('.vk-mig-log');
		if (!$log.length || !text) return;
		$log.prop('hidden', false).append(document.createTextNode(text + '\n'));
		$log.scrollTop($log[0].scrollHeight);
	};

	const run = function($button){
		const $card = $button.closest('.card');
		const $progress = $card.find('.vk-mig-progress');
		const action = $button.data('action');
		if ($button.data('confirm') && !window.confirm($button.data('confirm'))) return;
		$card.find('.vk-mig-run').prop('disabled', true);
		let first = true;
		let rounds = 0;
		const step = function(){
			const data = first && $button.data('reset') ? { reset: 1 } : {};
			first = false;
			$progress.text('Folyamatban…');
			post(action, data).done(function(response){
				if (!response.success){
					const retry = response.data && response.data.retry;
					log($card, (response.data && response.data.message) || 'Hiba');
					if (retry){ $progress.text('Várakozás ' + retry + ' mp…'); setTimeout(step, retry * 1000); return; }
					$progress.text('Megszakítva: ' + ((response.data && response.data.message) || 'hiba'));
					$card.find('.vk-mig-run').prop('disabled', false);
					return;
				}
				$progress.text(response.data.message.split('\n')[0]);
				log($card, response.data.message);
				rounds++;
				if (response.data.done){
					$progress.text('Kész. ' + response.data.message.split('\n')[0] + ' – az oldal frissül…');
					setTimeout(function(){ window.location.reload(); }, 1200);
					return;
				}
				step();
			}).fail(function(xhr){
				log($card, 'Kapcsolati hiba (' + xhr.status + '), újrapróbálás 10 mp múlva…');
				setTimeout(step, 10000);
			});
		};
		step();
	};

	$(document).on('click', '.vk-mig-run', function(e){ e.preventDefault(); run($(this)); });

	$(document).on('click', '.vk-mig-save', function(e){
		e.preventDefault();
		const data = {};
		$('.vk-mig-setting').each(function(){ if (this.name && this.value !== '') data[this.name] = this.value; });
		if ($(this).data('forget')) data.forget_key = 1;
		post('save', data).done(function(response){
			window.alert(response.success ? response.data.message : 'Hiba a mentéskor.');
			window.location.reload();
		});
	});

	$(document).on('change', '.vk-mig-upload', function(){
		if (!this.files.length) return;
		const form = new FormData();
		form.append('action', 'vk_mig_upload');
		form.append('nonce', cfg.nonce);
		form.append('file', this.files[0]);
		$.ajax({ url: cfg.ajax, method: 'POST', data: form, processData: false, contentType: false }).done(function(response){
			window.alert(response.success ? response.data.message : (response.data && response.data.message) || 'Hiba');
			window.location.reload();
		});
	});

	// Áttekintés: soronkénti kategóriaváltás (azonnal jóváhagy), csoportos műveletek.
	$(document).on('change', '.vk-mig-term', function(){
		const $select = $(this);
		if (!$select.val()) return;
		$select.prop('disabled', true);
		post('set', { ids: [ $select.data('id') ], term: $select.val(), todo: '' }).always(function(){ $select.prop('disabled', false); $select.closest('tr').find('.vk-badge').attr('class', 'vk-badge ready').text('Jóváhagyva'); });
	});
	$(document).on('change', '.vk-mig-all', function(){ $('.vk-mig-row').prop('checked', this.checked); });
	$(document).on('click', '.vk-mig-bulk', function(e){
		e.preventDefault();
		const ids = $('.vk-mig-row:checked').map(function(){ return this.value; }).get();
		if (!ids.length){ window.alert('Jelölj ki legalább egy terméket.'); return; }
		const todo = $(this).data('todo');
		const term = $('.vk-mig-bulk-term').val();
		if (todo === '' && !term){ window.alert('Válassz kategóriát.'); return; }
		post('set', { ids: ids, todo: todo, term: todo === '' ? term : 0 }).done(function(response){
			$('.vk-mig-bulk-msg').text(response.success ? response.data.message : 'Hiba');
			setTimeout(function(){ window.location.reload(); }, 600);
		});
	});

})(jQuery);
