(function(blocks, element, blockEditor, components){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/section', {
		edit: function({ setAttributes, attributes}) {
			let classNames = [ 'iu-section' ];
			if (attributes.lightText) classNames.push('iu-section-light');
			if (attributes.fullWidth) classNames.push('iu-section-fullwidth');
			if (attributes.className) classNames.push(attributes.className);

			return el( 'div',
				useBlockProps({
					className: classNames.join(' ')
				}),
				el( blockEditor.InnerBlocks, 
					{ 
						allowedBlocks: [ 'iu/row' ],
						template: [ [ 'iu/row' ] ],
						templateLock: attributes.templateLock
					}
				),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
						el( components.ToggleControl,
							{
								label: 'Full width',
								checked: attributes.fullWidth,
								onChange: function(newValue){
									setAttributes({ fullWidth: newValue });
								}
							}
						),
						el( components.ToggleControl,
							{
								label: 'Light text',
								checked: attributes.lightText,
								onChange: function(newValue){
									setAttributes({ lightText: newValue });
								}
							}
						)	
					)
				)
			);
		},
		save: function({ attributes }){
			let classNames = [ 'iu-section' ];
			if (attributes.lightText) classNames.push('iu-section-light');
			if (attributes.fullWidth) classNames.push('iu-section-fullwidth');
			if (attributes.className) classNames.push(attributes.className);

			var props = { 
				className: classNames.join(' ')
			};

			return el( 'section',
				props,
				el( blockEditor.InnerBlocks.Content, {})
			);
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components
);