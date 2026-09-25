(function(blocks, element, blockEditor, components, serverSideRender){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/query', {
		edit: function({ setAttributes, attributes }){
			return el( 'div',
				useBlockProps(),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody, 
						{
							title: 'Settings'
						},
						el( components.CheckboxControl,
							{
								label: 'Use main query params',
								checked: attributes.main_query,
								onChange: function(newValue){
									setAttributes({ main_query: newValue });
								}
							}
						),
						attributes.main_query ? '' :
						el( components.TextareaControl,
							{
								label: 'Query params',
								value: attributes.params,
								onChange: function(newValue){
									setAttributes({ params: newValue });
								}
							}
						),
						el( components.TextareaControl,
							{
								label: 'Template',
								value: attributes.template,
								onChange: function(newValue){
									setAttributes({ template: newValue });
								}
							}
						),
						el( components.SelectControl,
							{
								label: 'Columns',
								value:[ attributes.columns ],
								options: [
									{ label: '1', value: '1' },
									{ label: '2', value: '2' },
									{ label: '3', value: '3' },
									{ label: '4', value: '4' },
								],
								onChange: function(newValue){
									setAttributes({ columns: newValue });
								}
							}
						),
						el( components.ToggleControl,
							{
								label: 'Paging',
								checked: attributes.paging,
								onChange: function(newValue){
									setAttributes({ paging: newValue });
								}
							}
						)
					)
				),
				el(	components.Disabled,
					{},
					el( serverSideRender,
						{
							block: 'iu/query',
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