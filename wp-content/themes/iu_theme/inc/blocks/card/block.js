(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/card', {
		edit: function({ setAttributes, attributes }){
			const hasBackground = ((attributes.bgColor != '') && (attributes.bgColor != undefined)) || (attributes.bgGradient != null) || (attributes.bgImage != '');

			return el('div',
				useBlockProps(),
				el( 'div',
					{
						className: hasBackground ? 'iu-card-has-background' : ''
					},
					attributes.imageID != null ? el( 'div',
						{
							className: 'iu-card-image'
						},
						el( 'img',
							{
								src: attributes.imageURL
							}
						)
					) : '',
					el( 'div',
						{
							className: 'iu-card-content'
						},
						el( 'h4',
							{
								className: 'iu-card-heading iu-heading'
							},
							attributes.heading
						),
						el( 'p',
							{
								className: 'iu-card-text iu-text' 
							},
							attributes.text
						),
						attributes.button != '' ? el( 'div',
							{
								className: 'iu-button-group'
							},
							el( 'a',
								{
									className: 'iu-button iu-button-default'
								},
								attributes.button
							)
						) : ''
					)
				),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody, 
						{
							title: 'Settings'
						},
						el( components.BaseControl,
							{
								label: 'Image'
							},
							attributes.imageURL == '' ? el( blockEditor.MediaUploadCheck,
								{},
								el( blockEditor.MediaUpload,
									{
										title: 'Select image',
										value: attributes.imageID,
										allowedTypes: ['image'],
										onSelect: function(image){
											setAttributes({ 
												imageID: image.id,
												imageURL: image.url,
												imageThumbURL: image.sizes.thumbnail.url
											});
										},
										render: function(props){
											return el( components.Button,
												{
													className: 'editor-post-featured-image__toggle',
													onClick: props.open,
												},
												'Select image'
											);
										}
									}
								)
							) : el( blockEditor.MediaUploadCheck,
								{},
								el( 'img',
									{
										src: attributes.imageThumbURL,
										style: {
											display: 'block'
										}
									}
								),
								el ( components.Button,
									{
										isLink: true,
										isDestructive: true,
										onClick: function(){
											setAttributes({ 
												imageID: null,
												imageURL: '',
												imageThumbURL: ''
											});
										},
										style: {
											marginBottom: '24px'
										}
									},
									'Remove image'
								)
							)
						),
						el( components.TextControl,
							{
								label: 'Heading',
								value: attributes.heading,
								onChange: function(newValue){
									setAttributes({ heading: newValue });
								}
							}
						),
						el( components.TextareaControl,
							{
								label: 'Text',
								value: attributes.text,
								onChange: function(newValue){
									setAttributes({ text: newValue });
								}
							}
						),
						el( components.TextControl,
							{
								label: 'Button',
								value: attributes.button,
								onChange: function(newValue){
									setAttributes({ button: newValue });
								}
							}
						)
					)
				)
			)
		},
		save: function({ attributes }){
			var hasBackground = ((attributes.bgColor != '') && (attributes.bgColor != undefined)) || (attributes.bgGradient != null) || (attributes.bgImage != '');

			let classNames = [ 'iu-card' ];
			if (hasBackground) classNames.push('iu-card-has-background');

			return el( 'div',
				{
					className: classNames.join(' ')
				},
				attributes.imageID != null ? el( 'div',
					{
						className: 'iu-card-image'
					},
					el( 'img',
						{
							src: attributes.imageURL
						}
					)
				) : '',
				el( 'div',
					{
						className: 'iu-card-content'
					},
					el( 'h4',
						{
							className: 'iu-card-heading iu-heading'
						},
						attributes.heading
					),
					el( 'p',
						{
							className: 'iu-card-text iu-text' 
						},
						attributes.text
					),
					attributes.button != '' ? el( 'div',
						{
							className: 'iu-button-group'
						},
						el( 'a',
							{
								className: 'iu-button iu-button-default',
								href: attributes.linkUrl != '' ? attributes.linkUrl : null,
								target: attributes.linkNewTab ? '_blank' : null,
								rel: attributes.linkNewTab && 'noopener noreferrer'
							},
							attributes.button
						)
					) : ''
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