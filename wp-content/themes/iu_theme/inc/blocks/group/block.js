(function(blocks, element, blockEditor, components){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/group', {
		edit: function({ setAttributes, attributes}) {
			let classNames = ['iu-group'];
			if (attributes.direction === 'column') classNames.push('iu-group-direction-column');
			if (attributes.equal) classNames.push('iu-group-equal');
			if (attributes.horizontal !== '') classNames.push('iu-group-horizontal-'+attributes.horizontal);
			if (attributes.vertical !== '') classNames.push('iu-group-vertical-'+attributes.vertical);

			if (attributes.className) classNames.push(attributes.className);

			return el( 'div',
				useBlockProps({
					className: classNames.join(' ')
				}),
				el( blockEditor.InnerBlocks, 
					{
						orientation: 'horizontal'
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
								label: 'Direction',
								value: attributes.direction,
								options: [
									{ label: 'Row', value: '' },
									{ label: 'Column', value: 'column' }
								],
								onChange: function(newValue){
									setAttributes({ direction: newValue });
								}
							}
						),
						el( components.CheckboxControl,
							{
								label: 'Equal width',
								checked: attributes.equal,
								onChange: function(newValue){
									setAttributes({ equal: newValue });
								}
							}
						),
						el( components.SelectControl,
							{
								label: 'Horizontal',
								value: attributes.horizontal,
								options: [
									{ label: 'Left', value: '' },
									{ label: 'Center', value: 'center' },									
									{ label: 'Right', value: 'flex-end' },
									{ label: 'Space between', value: 'space-between' }
								],
								onChange: function(newValue){
									setAttributes({ horizontal: newValue });
								}
							}
						),
						el( components.SelectControl,
							{
								label: 'Vertical',
								value: attributes.vertical,
								options: [
									{ label: 'Top', value: '' },
									{ label: 'Center', value: 'center' },
									{ label: 'Bottom', value: 'flex-end' }
								],
								onChange: function(newValue){
									setAttributes({ vertical: newValue });
								}
							}
						)
					)
				)
			);
		},
		save: function({ attributes }){
			let classNames = [ 'iu-group' ];
			if (attributes.direction === 'column') classNames.push('iu-group-direction-column');
			if (attributes.equal) classNames.push('iu-group-equal');
			if (attributes.horizontal !== '') classNames.push('iu-group-horizontal-'+attributes.horizontal);
			if (attributes.vertical !== '') classNames.push('iu-group-vertical-'+attributes.vertical);

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