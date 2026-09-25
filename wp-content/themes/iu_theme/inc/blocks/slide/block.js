(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/slide', {
		edit: function({ setAttributes, attributes }){
			const classNames = [ 'iu-slide' ];
			const style = iu.blockUtils.background.styles(attributes);

			return el( 'div',
				useBlockProps({
					className: classNames.join(' '),
					style: style
				}),
				el( 'div',
					{
						className: 'iu-slide-inner'
					},
					el( blockEditor.InnerBlocks, {} )
				),
				el( blockEditor.InspectorControls,
					{},
					el( iu.blockUtils.background.panel,
						{
							attributes: attributes,
							setAttributes: setAttributes
						}
					)
				)
			);
		},
		save: function({ attributes }){
			const classNames = [ 'iu-slide' ];
			const style = iu.blockUtils.background.styles(attributes);

			return el( 'div',
				{
					className: classNames.join(' ')
				},
				el( 'div',
					{
						className: 'iu-slide-bg',
						style: style
					}
				),
				el( 'div',
					{
						className: 'iu-slide-inner'
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