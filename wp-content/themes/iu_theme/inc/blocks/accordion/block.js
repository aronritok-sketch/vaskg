(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/accordion', {
		edit: function({ setAttributes, attributes, clientId }){
			const classNames = ['iu-accordion'];

			return el( 'div',
				useBlockProps({
					className: classNames.join(' ')
				}),
				el( blockEditor.InnerBlocks, 
					{ 
						allowedBlocks: [ 'iu/accordion-item' ],
						template: [ [ 'iu/accordion-item' ] ]
					}
				)
			);
		},
		save: function({ attributes }){
			const classNames = ['iu-accordion'];
			
			return el( 'div',
				{
					className: classNames.join(' ')
				},
				el( blockEditor.InnerBlocks.Content, {} )
			);
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components
);