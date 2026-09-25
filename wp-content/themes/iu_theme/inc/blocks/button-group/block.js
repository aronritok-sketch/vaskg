(function(blocks, element, blockEditor, components){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/button-group', {
		edit: function({ setAttributes, attributes}) {
			let classNames = [];
			if (attributes.align !== '') classNames.push('iu-button-group-justify-'+attributes.align);

			if (attributes.className) classNames.push(attributes.className);

			return el( 'div',
				useBlockProps({
					className: classNames.join(' ')
				}),
				el( blockEditor.InnerBlocks, 
					{
						allowedBlocks: [ 'iu/button' ],
						orientation: 'horizontal',
						template: [[ 'iu/button' ]]
					}
				),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody, 
						{
							title: 'Settings'
						},
						el( components.SelectControl,
							{
								label: 'Alignment',
								value: attributes.align,
								options: [
									{ label: 'Left', value: 'flex-start' },
									{ label: 'Center', value: 'center' },									
									{ label: 'Right', value: 'flex-end' }
								],
								onChange: function(newValue){
									setAttributes({ align: newValue });
								}
							}
						)
					)
				)
			);
		},
		save: function({ attributes }){
			let classNames = [ 'iu-button-group' ];
			if (attributes.align !== '') classNames.push('iu-button-group-justify-'+attributes.align);

			if (attributes.className) classNames.push(attributes.className);

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