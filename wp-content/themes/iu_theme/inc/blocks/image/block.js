(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/image', {
		edit: function({ setAttributes, attributes }){
			let classNames = [ 'iu-image' ];
			let wrapStyle = {};
			let imageStyle = {};

			if (attributes.className) classNames.push(attributes.className);

			if (attributes.crop){
				classNames.push('iu-image-crop')
				wrapStyle.width = attributes.width;
				wrapStyle.height = '0';
				wrapStyle.paddingTop = attributes.height;
			} else {
				if (attributes.width != ''){
					imageStyle.width = attributes.width;
				}
				if (attributes.height != ''){
					imageStyle.height = attributes.height;
				}
			}

			if (attributes.align == 'center'){
				classNames.push('iu-image-justify-center');
			} else if (attributes.align == 'right'){
				classNames.push('iu-image-justify-right');
			}

			return el( 'div',
				useBlockProps(),
				attributes.imageURL == '' ? el( 'div',
					{
						className: 'iu-image-placeholder'
					}
				) : el( 'div',
					{
						className: classNames.join(' ')
					},
					el( 'div',
						{
							className: 'iu-image-wrap',
							style: wrapStyle
						},
						el( 'img',
							{
								src: attributes.imageURL,
								style: imageStyle
							}
						)
					)
				), 
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
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
												imageThumbURL: image.mime == 'image/svg+xml' ? image.url : (image.sizes.thumbnail ? image.sizes.thumbnail.url : image.sizes.full.url)
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
								),
								el( components.ToggleControl,
									{
										label: 'Crop image',
										checked: attributes.crop,
										onChange: function(newValue){
											setAttributes({ crop: newValue });
										}
									}
								),
								el( 'div',
									{
										style: {
											display: "flex",
											gap: '7%'
										}
									},
									el( components.TextControl,
										{
											label: 'Width',
											value: attributes.width,
											onChange: function(newValue){
												setAttributes({ width: newValue });
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
									)
								),
								el( components.SelectControl,
									{
										label: 'Alignment',
										value: attributes.align,
										options: [
											{ label: 'Left', value: '' },
											{ label: 'Center', value: 'center' },									
											{ label: 'Right', value: 'right' }
										],
										onChange: function(newValue){
											setAttributes({ align: newValue });
										}
									}
								)
							)
						),
						
					)
				)
			);
		},
		save: function({ attributes }){
			let wrapStyle = {};
			let classNames = [ 'iu-image' ];
			let imageStyle = {};

			if (attributes.className) classNames.push(attributes.className);

			if (attributes.crop){
				classNames.push('iu-image-crop')
				wrapStyle.width = attributes.width;
				wrapStyle.height = '0';
				wrapStyle.paddingTop = attributes.height;
			} else {
				if (attributes.width != ''){
					imageStyle.width = attributes.width;
				}
				if (attributes.height != ''){
					imageStyle.height = attributes.height;
				}
			}

			if (attributes.align == 'center'){
				classNames.push('iu-image-justify-center');
			} else if (attributes.align == 'right'){
				classNames.push('iu-image-justify-right');
			}

			var wrapProps = {
				className: 'iu-image-wrap',
				style: wrapStyle
			};

			if (attributes.linkUrl != ''){
				wrapProps.href = attributes.linkUrl;
				if (attributes.linkNewTab){
					wrapProps.target = '_blank';
				}
			}

			return el( 'div',
				{
					className: classNames.join(' ')
				},
				el ( attributes.linkUrl != '' ? 'a' :'div',
					wrapProps,
					el( 'img',
						{
							src: attributes.imageURL,
							style: imageStyle
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
	window.wp.components
);