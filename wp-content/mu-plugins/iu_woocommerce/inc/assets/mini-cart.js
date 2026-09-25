(function($){

	const update = function(e, fragment, hash, $button){
		const $cart = $(fragment['div.widget_shopping_cart_content']);
		const removing = $button.hasClass('remove');

		console.log(fragment, $cart, removing);

		$('.iu-woocommerce-mini-cart__content .inner').html($cart.html());
		
		let cartCount = 0;
		let quantityEls = $cart.find('.quantity');

		quantityEls.each(function(){
			let q = $(this).text().split(' ×')[0];
			cartCount += parseInt($(this).text().split(' ×')[0]);
		});

		$('.iu-woocommerce-mini-cart__badge').text(cartCount > 0 ? cartCount : '');

		if (!removing){
			if ($('.iu-woocommerce-mini-cart__dropdown').length > 0) modal($button, $cart);
			else $('.iu-woocommerce-mini-cart__trigger').trigger('click');
		}
	};

	const modal = function($button, $cart){
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


	$(document).ready(function(){

		$(document.body).on('added_to_cart', update);
		$(document.body).on('removed_from_cart', update);

		$(document).on('click','.iu-woocommerce-mini-cart__trigger[href="#"]',function(e){
			e.preventDefault();
			$(this).parent().find('.iu-woocommerce-mini-cart__dropdown, .iu-woocommerce-mini-cart__drawer').toggleClass('open');
		});
		$(document).on('click','.iu-woocommerce-mini-cart__close',function(e){
			e.preventDefault();
			$(this).parents('.iu-woocommerce-mini-cart__dropdown, .iu-woocommerce-mini-cart__drawer').toggleClass('open');
		});

	});

})(jQuery)