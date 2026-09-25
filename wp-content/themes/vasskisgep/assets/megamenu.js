(function(){

	/*
	 * Termékek megamenü (inc/megamenu.php). Asztali nézetben rámutatásra nyílik,
	 * a panel a fejléc alá, a tartalom szélességére igazodik.
	 */

	const desktop = window.matchMedia('(min-width: 992px)');

	document.querySelectorAll('li.vk-has-mega').forEach(function(item){
		const panel = item.querySelector('.vk-mega');
		if (!panel) return;
		const link = item.querySelector(':scope > a');
		let openTimer = null;
		let closeTimer = null;
		let suppressUntil = 0;

		const position = function(){
			const bar = item.closest('.iu-section') || item.closest('header');
			const row = bar.querySelector('.iu-row') || bar;
			const barRect = bar.getBoundingClientRect();
			const rowRect = row.getBoundingClientRect();
			panel.style.top = Math.round(barRect.bottom) + 'px';
			panel.style.left = Math.round(rowRect.left) + 'px';
			panel.style.width = Math.round(rowRect.width) + 'px';
		};

		const open = function(){
			if (!desktop.matches || Date.now() < suppressUntil) return;
			clearTimeout(closeTimer);
			position();
			item.classList.add('vk-mega-open');
			if (link) link.setAttribute('aria-expanded', 'true');
		};
		// Bezárás után a fókusz visszakerül a menüpontra; ez ne nyissa újra a panelt.
		const close = function(refocus){
			clearTimeout(openTimer);
			if (refocus){
				suppressUntil = Date.now() + 400;
				if (link) link.focus();
			}
			item.classList.remove('vk-mega-open');
			if (link) link.setAttribute('aria-expanded', 'false');
		};

		if (link){
			link.setAttribute('aria-haspopup', 'true');
			link.setAttribute('aria-expanded', 'false');
		}

		item.addEventListener('mouseenter', function(){
			clearTimeout(closeTimer);
			openTimer = setTimeout(open, 120);
		});
		// Az X-szel bezárt panel a menüpontra visszatérve újra nyíljon (a li-ből nem léptünk ki).
		if (link) link.addEventListener('mouseenter', function(){
			clearTimeout(closeTimer);
			openTimer = setTimeout(open, 120);
		});
		item.addEventListener('mouseleave', function(){
			clearTimeout(openTimer);
			closeTimer = setTimeout(function(){ close(false); }, 250);
		});
		// Billentyűzettel: fókuszra nyílik, a panelből kilépve záródik.
		item.addEventListener('focusin', open);
		item.addEventListener('focusout', function(e){
			if (!item.contains(e.relatedTarget)) close(false);
		});

		panel.querySelector('.vk-mega-close').addEventListener('click', function(e){
			e.preventDefault();
			close(true);
		});

		panel.querySelectorAll('.vk-mega-tab').forEach(function(tab){
			tab.addEventListener('click', function(){
				panel.querySelectorAll('.vk-mega-tab').forEach(function(t){
					t.classList.toggle('vk-active', t === tab);
					t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
				});
				panel.querySelectorAll('.vk-mega-pane').forEach(function(pane){
					pane.classList.toggle('vk-active', pane.dataset.tab === tab.dataset.tab);
				});
			});
		});

		document.addEventListener('keydown', function(e){
			if (e.key === 'Escape' && item.classList.contains('vk-mega-open')){
				close(true);
			}
		});
		window.addEventListener('scroll', function(){
			if (item.classList.contains('vk-mega-open')) position();
		}, { passive: true });
		window.addEventListener('resize', function(){
			if (!desktop.matches) close(false);
			else if (item.classList.contains('vk-mega-open')) position();
		});
	});

})();
