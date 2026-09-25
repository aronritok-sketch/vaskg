(function(blocks, element, blockEditor, components){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/column', {
		edit: function({ setAttributes, attributes }) {
			const widthArgs = attributes.width.split('-');

			const style = {
				width: (100*parseInt(widthArgs[0])/parseInt(widthArgs[1]))+'%'
			};

			let classNames = ['iu-column'];
			if (attributes.align !== '') classNames.push('iu-column-align-'+attributes.align);
			if (attributes.className) classNames.push(attributes.className);

			return el( 'div',
				useBlockProps({
					className: classNames.join(' '),
					style: style
				}),
				el( blockEditor.InnerBlocks, 
					{
						template: [ [ 'core/paragraph' ] ],
						templateLock: attributes.templateLock
					}
				),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
						el( components.SelectControl,
							{
								label: 'Align',
								value: attributes.align,
								options: [
									{ label: 'None', value: '' },
									{ label: 'Top', value: 'top' },
									{ label: 'Center', value: 'center' },
									{ label: 'Bottom', value: 'bottom' }
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
			let classNames = ['iu-column', 'iu-column-'+attributes.width];
			if (attributes.align !== '') classNames.push('iu-column-align-'+attributes.align);
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