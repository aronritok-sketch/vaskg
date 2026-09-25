(function($){

	$('[href]').filter(function(){ return $(this).attr('href').indexOf('#modal[') === 0; }).off('click').on('click',function(e){
		e.preventDefault();

		const modalId = $(this).attr('href').split('#modal[')[1].split(']')[0];console.log(modalId);
		$('#'+modalId).addClass('open');
	});

	$('.iu-modal').on('click',function(e){
		if(e.target === this) $(this).find('.iu-modal-close').trigger('click');
	});

	$('.iu-modal-close').on('click',function(e){
		$(this).closest('.iu-modal').addClass('closing');
		
		const $modal = $(this).closest('.iu-modal');
		setTimeout(function(){
			$modal.removeClass('open').removeClass('closing');
		},300);
	});

})(jQuery);

function iu_open_modal(modalId){
	jQuery('#'+modalId).addClass('open');
}

function iu_close_modal(){
	jQuery('.iu-modal.open').find('.iu-modal-close').trigger('click');
}