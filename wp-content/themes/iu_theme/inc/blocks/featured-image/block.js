(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/featured-image', {
		edit: function({ setAttributes, attributes }){
			let { featuredImageId, featuredImage } = wp.data.useSelect(function(select){
				let id = select('core/editor').getEditedPostAttribute('featured_media');
				return {
					featuredImageId: id,
					featuredImage: select('core').getMedia(id)
				};
			});

			let classNames = [ 'iu-featured-image', 'iu-image' ];
			let wrapStyle = {};
			let imageStyle = {};

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
			} else if (attributes.align == 'flex-end'){
				classNames.push('iu-image-justify-flex-end');
			}

			if (attributes.className) classNames.push(attributes.className);

			return el( 'div',
				useBlockProps(),
				!featuredImage ? 
					attributes.imageURL ? el( 'div',
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
					) : el( 'div',
						{
							className: 'iu-image-placeholder'
						}
					)
				: el( 'div',
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
								src: featuredImage.source_url,
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
								label: 'Default'
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
								)
							)
						),
						el( 'div', 
							{},
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