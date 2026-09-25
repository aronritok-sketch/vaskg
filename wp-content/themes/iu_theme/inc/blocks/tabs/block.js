(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/tabs', {
		edit: function({ setAttributes, attributes, clientId }){
			const classNames = ['iu-tabs'];

			const childBlocks = wp.data.useSelect(function(select){
		        return select( 'core/block-editor' ).getBlock( clientId )?.innerBlocks || [];
		    }, [ clientId ] );

		    wp.element.useEffect(function(){
		        const currentData = childBlocks.map(function(child){
		        	return child.attributes.title;
	    		});

		        if ( JSON.stringify( attributes.tabs ) !== JSON.stringify( currentData ) ) {
		            setAttributes({ tabs: currentData });
		        }
		    }, [ childBlocks, attributes.tabs, setAttributes ] );

		    const tabs = attributes.tabs.map(function(title){
	        	return el( 'a',
		    		{},
		    		title
		    	);
	        });

			return el( 'div',
				useBlockProps({
					className: classNames.join(' ')
				}),
				el( 'div',
					{
						className: 'iu-tabs-tabs'
					},
					...tabs
				),
				el( 'div',
					{
						className: 'iu-tabs-contents'
					},
					el( blockEditor.InnerBlocks, 
						{ 
							allowedBlocks: [ 'iu/tab' ],
							template: [ [ 'iu/tab' ] ]
						}
					)
				),
				el( 'style',
					{},
					`#block-`+clientId+` .wp-block-iu-tab {
						display: none;
					}
					#block-`+clientId+`:not(:has(.is-selected)) .wp-block-iu-tab:first-child, #block-`+clientId+`:has(.is-selected) .wp-block-iu-tab.is-selected, #block-`+clientId+`:has(.is-selected) .wp-block-iu-tab:has(.is-selected) {
						display: block;
					}`
				),
			);
		},
		save: function({ attributes }){
			const classNames = ['iu-tabs'];

			const tabs = attributes.tabs.map(function(title){
	        	return el( 'a',
		    		{},
		    		title
		    	);
	        });
			
			return el( 'div',
				{
					className: classNames.join(' ')
				},
				el( 'div',
					{
						className: 'iu-tabs-tabs'
					},
					...tabs
				),
				el( 'div',
					{
						className: 'iu-tabs-contents'
					},
					el( blockEditor.InnerBlocks.Content, {} )
				)
			);
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components
);