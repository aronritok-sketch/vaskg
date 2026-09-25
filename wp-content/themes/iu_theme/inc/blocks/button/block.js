(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/button', {
		edit: function({ setAttributes, attributes }){
			let classNames = [ 'iu-button', 'iu-button-'+attributes.type ];

			if (attributes.className) classNames.push(attributes.className);

			return el( 'div',
				useBlockProps({
					className: classNames.join(' ')
				}),
				el('span',
					{
						className: 'iu-button-text'
					},
					el('span',
						{},
						attributes.text
					)
				),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
						el( components.TextControl,
							{
								label: 'Text',
								value: attributes.text,
								onChange: function(newValue){
									setAttributes({ text: newValue });
								}
							}
						),
						el( components.SelectControl,
							{
								label: 'Type',
								value: attributes.type,
								options: [
									{ label: 'Default', value: 'default' },
									{ label: 'Highlight', value: 'highlight' },
									{ label: 'Inverted', value: 'invert' },
									{ label: 'Link', value: 'link' },
								],
								onChange: function(newValue){
									setAttributes({ type: newValue });
								}
							}
						)					
					)
				)
			);
		},
		save: function({ attributes }){
			let classNames = [ 'iu-button', 'iu-button-'+attributes.type ];

			if (attributes.className) classNames.push(attributes.className);

			return el('a',
				{
					className: classNames.join(' '),
					href: attributes.linkUrl != '' ? attributes.linkUrl : null,
					target: attributes.linkNewTab ? '_blank' : null,
					rel: attributes.linkNewTab && 'noopener noreferrer'
				},
				el('span',
					{
						className: 'iu-button-text'
					},
					el('span',
						{},
						attributes.text
					)
				)
			);
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components
);