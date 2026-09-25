(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/tab', {
		edit: function({ setAttributes, attributes }){
			const classNames = ['iu-tab'];

			return el( 'div',
				useBlockProps({
					className: classNames.join(' ')
				}),
				el( blockEditor.InnerBlocks, {} ),
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
			const classNames = ['iu-tab'];
			
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