(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/test', {
		edit: function({ setAttributes, attributes }){
			return el( 'div',
				useBlockProps(),
				'test',
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
						el( iu.blockUtils.repeater.el,
							{
								value: attributes.slides,
								fields: [
									{ id: 'image', label: 'Image', type: 'image' },
									{ id: 'title', label: 'Title', type: 'text' },
									{ id: 'content', label: 'Content', type: 'textarea' }
								],
								onChange: function(newValue){
									setAttributes({ slides: newValue });
								}
							}
						),
						el( components.TextareaControl,
							{
								label: 'Params',
								value: attributes.params,
								onChange: function(newValue){
									setAttributes({ params: newValue });
								}
							}
						)
					)
				)
			);
		},
		save: function({ attributes }){
			return null;
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components
);