(function($){

	$('.mobile-toggle').on('click',function(){
		$(this).closest('.iu-menu-block-wrapper').toggleClass('iu-menu-open');
	});

	$('.iu-menu-trigger').on('click',function(e){
		e.preventDefault();
		$(this).parent().next('.sub-menu').toggleClass('active');
	});

	$('.iu-menu-back').on('click',function(){
		$(this).parent().toggleClass('active');
	});

})(jQuery)