(function($){

	$(document).ready(function(){

		$('.iu-woocommerce-search-trigger').on('click',function(){
			$(this).closest('.iu-woocommerce-search').addClass('active').find('.iu-woocommerce-search-input input').focus();
		});

		$('.iu-woocommerce-search-close').on('click',function(){
			$(this).closest('.iu-woocommerce-search').removeClass('active');
		});

		let currentAjaxRequest = null;
		$('.iu-woocommerce-search-input input').on('input',function(){
			const self = this;

			if (currentAjaxRequest !== null) currentAjaxRequest.abort();
			if ($(this).val() === ''){
				$(self).closest('.iu-woocommerce-search-form').find('.iu-woocommerce-search-results').html('');
				$(self).closest('.iu-woocommerce-search').removeClass('loading');
				return;
			}

			$(this).closest('.iu-woocommerce-search').addClass('loading');
			currentAjaxRequest = $.post(iu_woocommerce_search.ajax_url,{
				action: 'iu_woocommerce_search',
				security: iu_woocommerce_search.nonce,
				s: $(this).val()
			}, function(response){
				$(self).closest('.iu-woocommerce-search-form').find('.iu-woocommerce-search-results').html(response.data.html);
				$(self).closest('.iu-woocommerce-search').removeClass('loading');
			});
		});

	});

})(jQuery)