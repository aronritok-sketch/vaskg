(function(blocks, element, blockEditor, components, serverSideRender){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/breadcrumbs', {
		edit: function({ setAttributes, attributes }){
			return el( 'div',
				useBlockProps(),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody, 
						{
							title: 'Settings'
						},
						el( components.TextControl,
							{
								label: 'Separator',
								value: attributes.separator,
								onChange: function(newValue){
									setAttributes({ separator: newValue });
								}
							}
						),
						el( components.SelectControl,
							{
								label: 'Alignment',
								value: attributes.align,
								options: [
									{ label: 'Left', value: '' },
									{ label: 'Center', value: 'center' },									
									{ label: 'Right', value: 'right' }
								],
								onChange: function(newValue){
									setAttributes({ align: newValue });
								}
							}
						)
					)
				),
				el(	components.Disabled,
					{},
					el( serverSideRender,
						{
							block: 'iu/breadcrumbs',
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