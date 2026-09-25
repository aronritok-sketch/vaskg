(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu-woocommerce/product-dot', {
		edit: function({ setAttributes, attributes }){
			let classNames = [ 'iu-woocommerce-product-dot' ];
			
			return el( 'div',
				useBlockProps({
					className: classNames.join(' ')
				}),
				
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
						el( components.TextControl,
							{
								label: 'X (%)',
								value: attributes.x,
								onChange: function(newValue){
									setAttributes({ x: newValue });
								}
							}
						),
						el( components.TextControl,
							{
								label: 'Y (%)',
								value: attributes.y,
								onChange: function(newValue){
									setAttributes({ y: newValue });
								}
							}
						),
						el( components.SelectControl,
							{
								label: 'Placement',
								value: attributes.placement,
								options: [
									{ label: 'Left', value: '' },
									{ label: 'Top', value: 'top' },
									{ label: 'Right', value: 'right' },
									{ label: 'Bottom', value: 'bottom' }
								],
								onChange: function(newValue){
									setAttributes({ placement: newValue });
								}
							}
						),
						el( components.TextareaControl,
							{
								label: 'Content',
								value: attributes.content,
								onChange: function(newValue){
									setAttributes({ content: newValue });
								}
							}
						),
					)
				)
			);
		},
		save: function({ attributes }){
			let classNames = [ 'iu-woocommerce-product-dot' ];

			if (attributes.placement !== ''){
				classNames.push('iu-woocommerce-product-dot-'+placement);
			}

			let style = {
				left: attributes.x !== '' ? attributes.x+'%' : null,
				top: attributes.y !== '' ? attributes.y+'%' : null
			};

			return el( 'div',
				{
					className: classNames.join(' '),
					style: style,
				},
				el( 'a',
					{
						href: attributes.linkUrl !== '' ? attributes.linkUrl : null,
						target: attributes.linkNewTab ? '__blank' : null
					}
				),
				el( 'div',
					{
						className: 'iu-woocommerce-product-dot-bubble',
						dangerouslySetInnerHTML: {
							__html: attributes.content
						}
					}
				)
			)
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components
);