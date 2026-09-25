(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu-woocommerce/product-dots', {
		edit: function({ setAttributes, attributes }){
			let classNames = [ 'iu-woocommerce-product-dots' ];
			
			return el( 'div',
				useBlockProps({
					className: classNames.join(' ')
				}),
				attributes.imageURL !== '' ? el( 'img',
					{
						src: attributes.imageURL
					}
				) : el( 'div',
					{
						className: 'iu-woocommerce-product-dots-placeholer'
					}
				),
				el( blockEditor.InnerBlocks, 
					{ 
						allowedBlocks: [ 'iu-woocommerce/product-dot' ],
						template: [ [ 'iu-woocommerce/product-dot' ] ]
					}
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
								)
							)
						)						
					)
				)
			);
		},
		save: function({ attributes }){
			let classNames = [ 'iu-woocommerce-product-dots' ];

			return el( 'div',
				{
					className: classNames.join(' ')
				},
				attributes.imageURL !== '' ? el( 'img',
					{
						src: attributes.imageURL
					}
				) : el( 'div',
					{
						className: 'iu-woocommerce-product-dots-placeholer'
					}
				),
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