(function(blocks, element, blockEditor, components){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/logo-slider', {
		edit: function({ setAttributes, attributes}){
			const images = attributes.images || [];
			const renderImages = function(node){
				if (!node) return;

				node.innerHTML = '';
				images.forEach(function(image){
					const div = document.createElement('div');
					
					const img = document.createElement('img');
					img.src = image.thumb;
					div.appendChild(img);

					const remove = document.createElement('span');
					remove.className = 'dashicons dashicons-trash';
					remove.onclick = function(e){
						const index = jQuery(e.target.closest('div')).index();
						const smaller = [...images];
						smaller.splice(index, 1);
						setAttributes({ images: smaller });
					};
					div.appendChild(remove);

					node.appendChild( div );
				});

				jQuery(node).sortable({
					tolerance: 'pointer',
					start: function(e, ui){
						ui.item.data('index', ui.item.index());
					},
					update: function(e, ui){
						const oldIndex = ui.item.data('index');
						const newIndex = ui.item.index();

						if (oldIndex == newIndex) return;

						const reordered = [...images];
						const [ removed ] = reordered.splice(oldIndex, 1);
						reordered.splice(newIndex, 0, removed);
						setAttributes({ images: reordered });
					}
				});
			};

			return el('div',
				useBlockProps({
					className: 'iu-logo-slider'
				}),
				'logo-slider',
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
						el( components.BaseControl,
							{
								label: 'Images'
							},
							!attributes.images.length ? el( blockEditor.MediaUploadCheck,
								{},
								el( blockEditor.MediaUpload,
									{
										title: 'Select images',
										value: null,
										multiple: true,
										allowedTypes: ['image'],
										onSelect: function(imgs){
											setAttributes({
												images: imgs.map(function(img){
													return {
														id: img.id,
														url: img.url,
														thumb: img.mime == 'image/svg+xml' ? img.url : img.sizes.thumbnail.url
													}
												})
											});
										},
										render: function(props){
											return el( components.Button,
												{
													className: 'editor-post-featured-image__toggle',
													onClick: props.open,
												},
												'Select images'
											);
										}
									}
								)
							) : el( blockEditor.MediaUploadCheck,
								{},
								el( 'div',
									{
										className: 'iu-logo-slider-images',
										ref: renderImages
									}
								),
								el( blockEditor.MediaUpload,
									{
										title: 'Select images',
										value: null,
										multiple: true,
										allowedTypes: ['image'],
										onSelect: function(imgs){
											const add = imgs.map(function(img){
												return {
													id: img.id,
													url: img.url,
													thumb: img.mime == 'image/svg+xml' ? img.url : img.sizes.thumbnail.url
												};
											});
											setAttributes({ images: images.concat(add) });
										},
										render: function(props){
											return el( components.Button,
												{
													className: 'editor-post-featured-image__toggle',
													onClick: props.open,
													style: {
														marginTop: '10px'
													}
												},
												'Add images'
											);
										}
									}
								)
							)
						)
					)
				)
			)
		},
		save: function({attributes}){
			const imageEls = [];

			attributes.images.forEach(function(image){
				imageEls.push(
					el( 'div',
						{
							className: 'iu-logo-slider-slide'
						},
						el( 'div',
							{
								className: 'iu-logo-slider-wrap'
							},
							el( 'img',
								{
									src: image.url
								}
							)
						)
					)
				);
			});

			return el( 'div',
				{
					className: 'iu-logo-slider'
				},
				el( 'div',
					{
						className: 'iu-logo-slider-sliders'
					},
					el( 'div',
						{
							className: 'iu-logo-slider-slider'
						},
						...imageEls
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