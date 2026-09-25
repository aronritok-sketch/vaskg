(function(blocks, element, blockEditor, components, serverSideRender){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/terms', {
		edit: function({ setAttributes, attributes}){
			return el( 'div',
				useBlockProps(),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
						el( components.TextControl,
							{
								label: 'Taxonomy',
								value: attributes.taxonomy,
								onChange: function(newValue){
									setAttributes({ taxonomy: newValue });
								}
							}
						),
						el( components.TextControl,
							{
								label: 'All text',
								value: attributes.all_text,
								onChange: function(newValue){
									setAttributes({ all_text: newValue });
								}
							}
						),
						el( components.CheckboxControl,
							{
								label: 'Show post counts',
								checked: attributes.post_counts,
								onChange: function(newValue){
									setAttributes({ post_counts: newValue });
								}
							}
						),
						el( components.CheckboxControl,
							{
								label: 'Show as buttons',
								checked: attributes.buttons,
								onChange: function(newValue){
									setAttributes({ buttons: newValue });
								}
							}
						)
					)
				),
				el(	components.Disabled,
					{},
					el( serverSideRender,
						{
							block: 'iu/terms',
							attributes: attributes
						}
					)
				)
			);
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.serverSideRender
);