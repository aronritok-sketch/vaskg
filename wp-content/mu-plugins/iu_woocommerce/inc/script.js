(function($){

	/*$('.iu-woocommerce-add-to-cart').find('.iu-button').each(function(){
		$(this).attr('data-quantity', $(this).parents('.iu-woocommerce-add-to-cart').find('input.qty').attr('min'));
	});

	$(document).on('change', '.quantity input.qty', function(e){
		let min = parseInt(e.target.getAttribute('min'));
		let max = parseInt(e.target.getAttribute('max'));
		let step = parseInt(e.target.getAttribute('step'));
		if (min && e.target.value < min) e.target.value = min;
		if (max && e.target.value > max) e.target.value = max;
		if (step && step > 1 && e.target.value % step > 0) e.target.value -= e.target.value % step;

		if (e.target.closest('.iu-woocommerce-add-to-cart'))
			e.target.closest('.iu-woocommerce-add-to-cart').querySelector('.add_to_cart_button').dataset.quantity = e.target.value;
	});*/
	
	$(document).on('click', '.iu-woocommerce-quantity-control.minus', function(e){
		const input = e.target.parentElement.querySelector('input.qty');
		if (input.type != 'number') return;

		input.stepDown();
		$(input).trigger('input');
	});

	$(document).on('click', '.iu-woocommerce-quantity-control.plus', function(e){
		const input = e.target.parentElement.querySelector('input.qty');
		if (input.type != 'number') return;

		input.stepUp();
		$(input).trigger('input');
	});

	$(document).on('click', '.iu-woocommerce-list-views a', function(e){
		$(this).addClass('active').siblings().removeClass('active');
		if ($(this).hasClass('list')) $(this).closest('.woocommerce').removeClass('columns-3').addClass('columns-1').find('.products').removeClass('columns-3').addClass('columns-1');
		else $(this).closest('.woocommerce').removeClass('columns-1').addClass('columns-3').find('.products').removeClass('columns-1').addClass('columns-3');

		document.cookie = "iu-woocommerce-list-view="+($(this).hasClass('list') ? 'list' : 'grid')+"; path=/; SameSite=Lax; Secure";
	});

	/*const modal = function($button, $cart){
		let title = $button.parents('.product').find('.woocommerce-loop-product__title').text();
		if (!title) title = $button.parents('.product-details').find('h1.entry-title').text();
		
		const $product = $cart.find('.woocommerce-mini-cart-item').filter(function(index, item){
			return $(item).find('.product_title').text() == title;
		}).clone();

		$product.find('.remove').remove();

		const checkout_url = $cart.find('.checkout').attr('href');

		const $modal = $('.iu-woocommerce-modal').length > 0 ? $('.iu-woocommerce-modal') : $('<div class="iu-woocommerce-modal"></div>');
		$modal.html($('<div class="iu-woocommerce-modal"><div class="iu-woocommerce-modal-bg"></div><div class="iu-woocommerce-modal-dialog"><span class="close dashicons dashicons-no-alt"></span><div class="iu-woocommerce-modal-body"><div class="iu-woocommerce-modal-header"><h2 class="iu-heading"><span>A termék a kosárba került</span></h2></div><div class="iu-woocommerce-modal-content"><div class="iu-woocommerce-modal-product">'+$product[0].innerHTML+'</div></div><div class="iu-woocommerce-modal-footer"><a class="back iu-button iu-button-invert">Tovább vásárolok</a><a class="iu-button iu-button-default" href="'+checkout_url+'">Irány a péntzár</a></div></div></div>').html());

		$modal.find('.close, .back').on('click',function(){
			$(this).parents('.iu-woocommerce-modal').removeClass('iu-woocommerce-modal-show');
		});

		if (!$('body').find('.iu-woocommerce-modal').length) $('body').append($modal);

		setTimeout(function(){
			$modal.addClass('iu-woocommerce-modal-show');
		},100);
	};

	const update = function(e, fragment, hash, $button){
		let $cart = $(fragment['div.widget_shopping_cart_content']);

		const removing = $button.hasClass('remove');
		let quantity = 0;
		if (removing){
			quantity = parseInt($button.parent().find('.quantity').text().split(' ×')[0]);
		} else {
			quantity = parseInt($button.attr('data-quantity'));
		}

		const product_id = $button.data('product_id');
		const $input = $('.add_to_cart_button[data-product_id='+product_id+']').parent().find('input.qty');
		
		if ($input){
			let max = parseInt($input.attr('max')) || false;
			if ($input.hasClass('maxzero')) max = 0;

			if (max !== false){
				if (removing) $input.attr('max', max+quantity);
				else {
					$input.attr('max', max-quantity);
					if (max-quantity === 0) $input.addClass('maxzero');
				}
			}
		}

		$('.add_to_cart_button[data-product_id]').each(function(){
			const pid = $(this).data('product_id');
			const $input = $(this).parent().find('input.qty');
			let max = parseInt($input.attr('max')) || false;

			if ($input.hasClass('maxzero')) max = 0;
			if (max === false) return;
			
			if ($input.length){
				const categories = $input.parents('.iu-woocommerce-add-to-cart').data('category_ids');
				if (!categories.length) return;

				let cat_max = false;
				if (fragment.category_maxes){
					for(const [cat_id, qty] of Object.entries(fragment.category_maxes)){
						if (!categories.includes(parseInt(cat_id))) continue;
						if (cat_max === false || qty < cat_max) cat_max = qty;
					}
				}

				if (false !== cat_max){
					if (pid == product_id) {
						$input.attr('max', Math.min(cat_max, max));
						if (Math.min(cat_max, max) === 0) $input.addClass('maxzero');
					}
					else {
						$input.attr('max', cat_max);
						if (cat_max === 0) $input.addClass('maxzero');
					}
				}

				let newMax = parseInt($input.attr('max')) || false;
				if ($input.hasClass('maxzero')) newMax = 0;
				if (newMax === false) return;

				let $quantityReached = $input.parents('.iu-woocommerce-add-to-cart').next('.iu-woocommerce-quantity-reached');
				if (removing || (!removing && newMax > 0)){
					$input.parents('.iu-woocommerce-add-to-cart').show();
					$input.val($input.attr('min')).trigger('change');
					$quantityReached.hide();
				} else {
					$input.parents('.iu-woocommerce-add-to-cart').hide();
					$quantityReached.show();
				}
			}
		});

		$('.iu-woocommerce-mini-cart__dropdown .inner').html($cart.html());
		
		let cartCount = 0;
		let quantityEls = $cart.find('.quantity');

		quantityEls.each(function(){
			let q = $(this).text().split(' ×')[0];
			cartCount += parseInt($(this).text().split(' ×')[0]);
		});

		$('.iu-woocommerce-mini-cart__badge').text(cartCount > 0 ? cartCount : '');

		if (!removing){
			modal($button, $cart);
		}
	};

	$(document.body).on('added_to_cart',update);
	$(document.body).on('removed_from_cart',update);*/

	const updateCart = function(){
		$('.iu-woocommerce-add-to-cart').find('.iu-button').each(function(){
			$(this).attr('data-quantity', $(this).parents('.iu-woocommerce-add-to-cart').find('input.qty').attr('min'));
		});
	};
	$(document.body).on('updated_cart_totals',updateCart);

	$(document.body).on('updated_wc_div',function(e){
		let $form = $('.woocommerce-cart-form');
		let count = 0;

		if ($form.length == 0) $('.iu-woocommerce-mini-cart__badge').text('');
		else {
			$form.find('.quantity input.qty').each(function(){
				count += parseInt($(this).val());
			});
			$('.iu-woocommerce-mini-cart__badge').text(count);
		}
	});

	/*$('.woocommerce-product-details__short-description p').each(function(){
		let $self = $(this);
		let  $toggle = $('<span class="toggle" data-more="Több" data-less="Kevesebb">Több</span>');
		
		$(this).on('click', '.toggle', function(){
			if ($(this).text() == $(this).data('more')){
				$(this).text($(this).data('less'));
				$self.html($self.data('full')).append($(this).clone(true));
			} else {
				$(this).text($(this).data('more'));
				$self.html($self.data('short')).append($(this).clone(true));
			}
		});

		$(this).data('full', $(this).html());

		let el = document.createElement('div');
		el.innerHTML = $(this).html();
		
		let arr = Array.from(el.childNodes).map(function(e){
			return e.outerHTML || e.nodeValue.trim().split(' ').filter(function(t){ return t; });
		});
		let words = [].concat.apply([], arr);

		$(this).data('short', words.slice(0, Math.min(words.length, 16)).join(' ')+'...');
		$(this).html($(this).data('short')).append($toggle);
	});*/

	/*$('.woocommerce-cart-form__cart-item').each(function(){
		$(this).find('input.qty').each(function(){
			$(this).data('value', $(this).val());
		});

		$(this).find('input.qty').on('change',function(){
			const category_ids = $(this).parents('.woocommerce-cart-form__cart-item').data('category_ids');
			const change = parseInt($(this).val()) - parseInt($(this).data('value'));
			$(this).data('value', $(this).val());

			$('.woocommerce-cart-form__cart-item').not($(this).parents('.woocommerce-cart-form__cart-item')).filter(function(){
				return $(this).data('category_ids').filter(function(item){
					return category_ids.indexOf(item) !== -1;
				}).length;
			}).each(function(){
				const max = parseInt($(this).find('input.qty').attr('max'))-change;
				$(this).find('input.qty').attr('max', max);
			});
		});
	});*/

	$('input[type=tel]').each(function(){
		$(this).attr('placeholder', '+XX XX XXX XXXX');

		$(this).on('input',function(e){
			if (!e.target.value) return;

			const x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,2})(\d{0,3})(\d{0,4})/);
			e.target.value = '+'+x[1]+(x[2] ? ' '+x[2] : '')+(x[3] ? ' '+x[3] : '')+(x[4] ? ' '+x[4] : '');
		});
	});

})(jQuery);