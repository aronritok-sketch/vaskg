(function(blocks, element, blockEditor, components, data, serverSideRender){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var useSelect = data.useSelect;

	blocks.registerBlockType( 'iu/menu', {
		edit: function({ setAttributes, attributes}) {
			const { menus, hasResolvedMenus } = useSelect(function(select){
				const { getMenus, isResolving, hasFinishedResolution } = select('core');
				const menusParameters = [{ per_page: -1, context: 'view' }];

				return {
					menus: getMenus(...menusParameters),
					isResolvingMenus: isResolving('getMenus', menusParameters),
					hasResolvedMenus: hasFinishedResolution('getMenus', menusParameters ),
				};
			});

			const options = [];
			let buttonLabel = 'Select menu';

			if (hasResolvedMenus) {
				menus.forEach(function (item) {
					options.push({
						label: item.name,
						value: item.id
					});
				});
			}

			return el( 'div',
				useBlockProps(),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody, 
						{
							title: 'Settings'
						},
						el( components.SelectControl,
							{
								label: 'Menu',
								value: attributes.menu,
								options: options,
								onChange: function(newValue){
									setAttributes({ menu: newValue });
								}
							}
						)
					)
				),
				el( blockEditor.BlockControls,
					{
						group: 'block'
					},
					el( components.Button,
						{
							icon: 'menu-alt',
							label: 'Vertical menu',
							isPressed: attributes.vertical,
							onClick: function(){
								setAttributes({ vertical: !attributes.vertical });
							}
						}
					),
					el( components.Button,
						{
							icon: 'smartphone',
							label: 'Collapse on small screens',
							isPressed: attributes.collapse,
							onClick: function(){
								setAttributes({ collapse: !attributes.collapse });
							}
						}
					)
				),
				el(	components.Disabled,
					{},
					el( serverSideRender,
						{
							block: 'iu/menu',
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
	window.wp.data,
	window.wp.serverSideRender
);