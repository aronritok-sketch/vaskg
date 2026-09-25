(function($){

	const fillColor = function($el){
		const $min = $el.find('.iu-woocommerce-product-filter-price-input-min');
		const $max = $el.find('.iu-woocommerce-product-filter-price-input-max');
		const min = parseInt($min.attr('min'));
		const max = parseInt($max.attr('max'));
		const percent1 = ($min.val() - min) / (max - min) * 100;
        const percent2 = ($max.val() - min) / (max - min) * 100;
        
        $el.find('.iu-woocommerce-product-filter-price-range').css('--left',percent1 + "%").css('--width',(percent2 - percent1) + "%");
	};

	function format(number, separator) {
        let formatted = Number(number).toFixed(0);
        return formatted.replace(/\B(?=(\d{3})+(?!\d))/g, separator);
    }

	const updateMin = function($el){
		const $min = $el.find('.iu-woocommerce-product-filter-price-input-min');
		const $max = $el.find('.iu-woocommerce-product-filter-price-input-max');
		if ($max.val() - $min.val() <= 0) {
            $min.val($max.val());
        }
        $el.find('.iu-woocommerce-product-filter-price-label-min').text(format($min.val(), $el.data('separator')));
        fillColor($el);
	};

	const updateMax = function($el){
		const $min = $el.find('.iu-woocommerce-product-filter-price-input-min');
		const $max = $el.find('.iu-woocommerce-product-filter-price-input-max');
		if ($max.val() - $min.val() <= 0) {
            $max.val($min.val());
        }
        $el.find('.iu-woocommerce-product-filter-price-label-max').text(format($max.val(), $el.data('separator')));
        fillColor($el);
	};


	let currentAjaxRequest = null;
	const filter = function($el, extra){
		const urlParams = Object.fromEntries(new URLSearchParams(window.location.search));
		const params = typeof urlParams.orderby !== 'undefined' ? { orderby: urlParams.orderby } : {};

		const values = {};
		$el.serializeArray().forEach(function(item){
			if (item.name === 'product-categories'){
				if (values[item.name]) values[item.name] += ','+item.value;
				else values[item.name] = item.value;
			} else if (item.name === 'price-min'){
				if (item.value > parseInt($el.find('.iu-woocommerce-product-filter-price-input-min').attr('min'))) values[item.name] = item.value;
			} else if (item.name === 'price-max'){
				if (item.value < parseInt($el.find('.iu-woocommerce-product-filter-price-input-max').attr('max'))) values[item.name] = item.value;
			} else {
				values[item.name] = item.value;
			}
		});
		
		if (typeof extra !== 'undefined') Object.assign(values, extra);
		
		const query = Object.assign(params, values);
		const url = window.location.pathname+($.isEmptyObject(query) ? '' : '?'+(new URLSearchParams(query)).toString());
		history.pushState({}, "", url);

		if (currentAjaxRequest !== null) currentAjaxRequest.abort();
		
		$('.woocommerce[class*=columns]').addClass('loading');
		currentAjaxRequest = $.get(url, {
			action: 'iu_woocommerce_product_filter',
			security: iu_woocommerce_product_filter.nonce,
			...query
		}, function(response){
			$('.woocommerce[class*=columns]').replaceWith(response.data.html);
		});
	};

	$(document).on('input','.iu-woocommerce-product-filter-price-input-min',function(){
		updateMin($(this).closest('.iu-woocommerce-product-filter-price'));
	});

	$(document).on('input','.iu-woocommerce-product-filter-price-input-max',function(){
		updateMax($(this).closest('.iu-woocommerce-product-filter-price'));
	});

	$(document).ready(function(){
		$('.iu-woocommerce-product-filter-price').each(function(){
			updateMin($(this));
			updateMax($(this));
			fillColor($(this));
		});
	});

	$(document).on('click','.iu-woocommerce-product-filter-categories li > .dashicons',function(){
		$(this).siblings('ul').slideToggle();
		$(this).parent().toggleClass('open');
	});

	$(document).on('change','.iu-woocommerce-product-filter input[name][type=checkbox]',function(){
		if ($(this).prop('checked')){
			$(this).closest('li').find('ul input[type=checkbox]').prop('checked', false);
			$(this).closest('li').parent().closest('li').children('label').children('input[type=checkbox]').prop('checked', false);
		}
		
		filter($(this).closest('.iu-woocommerce-product-filter'));
	});

	$(document).on('click','.iu-woocommerce-product-filter-price .iu-button',function(){
		filter($(this).closest('.iu-woocommerce-product-filter'));
	});

	$(document).on('submit','.woocommerce-ordering',function(e){
		e.preventDefault();
	});
	$(document).on('change','.woocommerce-ordering select',function(){
		const urlParams = Object.fromEntries(new URLSearchParams(window.location.search));
		const params = { 'orderby': $(this).val() };
		if (urlParams['product-page']) params['product-page'] = urlParams['product-page'];
		
		filter($('.iu-woocommerce-product-filter'), params);
	});

	$(document).on('click','.woocommerce-pagination a', function(e){
		e.preventDefault();

		const params = Object.fromEntries(new URLSearchParams($(this).attr('href').split('?')[1]));
		filter($('.iu-woocommerce-product-filter'), { 'product-page': params['product-page'] });
	});

})(jQuery)