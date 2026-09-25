(function(blocks, element, blockEditor, components, serverSideRender){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/search', {
		edit: function({ setAttributes, attributes}) {
			return el('div', 
				useBlockProps({
					className: 'iu-search'
				}),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
						el( components.TextControl,
							{
								label: 'Placeholder',
								value: attributes.placeholder,
								onChange: function(newValue){
									setAttributes({ placeholder: newValue });
								}
							}
						)
					)
				),
				el(	components.Disabled,
					{},
					el( serverSideRender,
						{
							block: 'iu/search',
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