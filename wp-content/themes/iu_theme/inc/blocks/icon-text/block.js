(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/icon-text', {
		edit: function({ setAttributes, attributes }){
			return el( 'a',
				useBlockProps({
					className: 'iu-icon-text'
				}),
				attributes.svg !== '' ? el( 'span',
					{
						className: 'iu-icon iu-icon-'+attributes.type,
						dangerouslySetInnerHTML: { __html: attributes.svg },
						style: {
							'--iu-icon-svg-height': attributes.height !== '' ? attributes.height : null
						}
					}
				) : el( 'span',
					{
						className: 'iu-icon iu-icon-'+attributes.type,
					},
					el( components.Dashicon,
						{
							icon: attributes.icon,
							size: 24
						}
					)
				),
				el( 'span',
					{
						className: 'iu-icon-text-text'
					},
					attributes.text
				),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
						el( components.TextControl,
							{
								label: 'icon',
								value: attributes.icon,
								onChange: function(newValue){
									setAttributes({ icon: newValue });
								},
								help: el('a',
									{
										href: 'https://developer.wordpress.org/resource/dashicons/',
										target: '_blank'
									},
									'Dashicons'
								)
							}
						),
						el( components.TextareaControl,
							{
								label: 'SVG',
								value: attributes.svg,
								onChange: function(newValue){
									setAttributes({ svg: newValue });
								}
							}
						),
						el( components.TextControl,
							{
								label: 'Height',
								value: attributes.height,
								onChange: function(newValue){
									setAttributes({ height: newValue });
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
									{ label: 'Icon', value: 'icon' }
								],
								onChange: function(newValue){
									setAttributes({ type: newValue });
								}
							}
						),
						el( components.TextControl,
							{
								label: 'Text',
								value: attributes.text,
								onChange: function(newValue){
									setAttributes({ text: newValue });
								}
							}
						)
					)
				)
			);
		},
		save: function({ attributes }){
			return el ( 'a',
				{
					className: 'iu-icon-text',
					href: attributes.linkUrl != '' ? attributes.linkUrl : null,
					target: attributes.linkNewTab ? '_blank' : null,
					rel: attributes.linkNewTab && 'noopener noreferrer'
				},
				attributes.svg !== '' ? el( 'span',
					{
						className: 'iu-icon iu-icon-'+attributes.type,
						dangerouslySetInnerHTML: { __html: attributes.svg },
						style: {
							'--iu-icon-svg-height': attributes.height !== '' ? attributes.height : null
						}
					}
				) : el( 'span',
					{
						className: 'iu-icon iu-icon-'+attributes.type,
					},
					el( components.Dashicon,
						{
							icon: attributes.icon,
							size: 24
						}
					)
				),
				el( 'span',
					{
						className: 'iu-icon-text-text'
					},
					attributes.text
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