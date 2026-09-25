(function(blocks, element, blockEditor, components, serverSideRender){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/term-cloud', {
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
						)
					)
				),
				el(	components.Disabled,
					{},
					el( serverSideRender,
						{
							block: 'iu/term-cloud',
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