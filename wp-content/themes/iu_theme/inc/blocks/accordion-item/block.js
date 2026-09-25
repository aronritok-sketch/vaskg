(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/accordion-item', {
		edit: function({ setAttributes, attributes }){
			const classNames = ['iu-accordion-item'];

			return el( 'div',
				useBlockProps({
					className: classNames.join(' ')
				}),
				el( 'div',
					{
						className: 'iu-accordion-item-head'
					},
					attributes.title,
					el( 'span',
						{
							className: 'iu-accordion-item-toggle dashicons dashicons-arrow-right-alt2'
						}
					)
				),
				el( 'div',
					{
						className: 'iu-accordion-item-body'
					},
					el( blockEditor.InnerBlocks, {} )
				),				
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
						el( components.TextControl,
							{
								label: 'Title',
								value: attributes.title,
								onChange: function(newValue){
									setAttributes({ title: newValue });
								}
							}
						)
					)
				)
			);
		},
		save: function({ attributes }){
			const classNames = ['iu-accordion-item'];
			
			return el( 'div',
				{
					className: classNames.join(' ')
				},
				el( 'div',
					{
						className: 'iu-accordion-item-head'
					},
					attributes.title,
					el( 'span',
						{
							className: 'iu-accordion-item-toggle dashicons dashicons-arrow-right-alt2'
						}
					)
				),
				el( 'div',
					{
						className: 'iu-accordion-item-body'
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