(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/title', {
		edit: wp.data.withSelect(function(select){
			return {
				title: select('core/editor').getEditedPostAttribute('title'),
				postType: select('core/editor').getCurrentPostType()
			};
		})(function({ setAttributes, attributes, title, postType }){
			return el( 'div',
				useBlockProps({
					style: {
						textAlign: attributes.align
					}
				}),
				el( attributes.heading,
					{
						className: 'iu-title iu-heading'
					},
					postType === 'iu_template' ? '[[The Post Title]]' : title
				),
				el( blockEditor.BlockControls,
					{
						group: 'block'
					},
					el( blockEditor.AlignmentControl,
						{
							value: attributes.align,
							onChange: function(newValue){
								setAttributes({ align: newValue });
							}
						}
					)
				),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody, 
						{
							title: 'Settings'
						},
						el( components.SelectControl,
							{
								label: 'Heading',
								value: attributes.heading,
								options: [
									{ label: 'Heading 1', value: 'h1' },
									{ label: 'Heading 2', value: 'h2' },
									{ label: 'Heading 3', value: 'h3' },
									{ label: 'Heading 4', value: 'h4' },
									{ label: 'Heading 5', value: 'h5' },
									{ label: 'Heading 6', value: 'h6' },
								],
								onChange: function(newValue){
									setAttributes({ heading: newValue });
								}
							}
						)
					)
				)
			);
		})
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components
);