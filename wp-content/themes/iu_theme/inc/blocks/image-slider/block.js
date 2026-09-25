(function(blocks, element, blockEditor, components){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/image-slider', {
		edit: function({ setAttributes, attributes}){
			const slides = attributes.slides || [];
			const defaultContent = "<h4 class=\"iu-heading\">Subtitle</h4>\n<h2 class=\"iu-heading\">Title</h2>\n<p class=\"iu-text\">Lorem ipsum dolor sit amet</p>\n<div class=\"iu-button-group\">\n\t<a href=\"#\" class=\"iu-button iu-button-default\">Button 1</a>\n\t<a href=\"#\" class=\"iu-button iu-button-invert\">Button 2</a>\n</div>";

			const [opens, setOpens] = wp.element.useState(Array(slides.length).fill(false));

			const renderSlides = function(node){
				if (!node) return;

				node.innerHTML = '';
				slides.forEach(function(slide, index){
					const div = document.createElement('div');
					div.className = opens[index] ? 'open' : '';
					div.dataset.index = index;
					
					const handle = document.createElement('span');
					handle.className = 'dashicons dashicons-menu-alt2';
					div.appendChild(handle);

					const img = document.createElement('img');
					img.src = slide.image.thumb;
					div.appendChild(img);

					const remove = document.createElement('span');
					remove.className = 'dashicons dashicons-trash';
					remove.onclick = function(e){
						const idx = e.target.closest('div').dataset.index;

						const newOpens = [...opens];
						newOpens.slice(idx, 1);
						setOpens(newOpens);

						const newSlides = [...slides];
						newSlides.splice(idx, 1);
						setAttributes({ slides: newSlides });
					};
					div.appendChild(remove);

					const close = document.createElement('span');
					close.className = 'dashicons dashicons-arrow-down';
					close.onclick = function(e){
						const idx = e.target.closest('div').dataset.index;
						const newOpens = [...opens];
						newOpens[idx] = !newOpens[idx];
						setOpens(newOpens);
					};
					div.appendChild(close);

					const content = document.createElement('textarea');
					content.id = 'iu-image-slider-slide_'+index;
					content.value = slide.content;
					content.rows = 4;
					content.onblur = function(e){
						const newSlides = [...slides];
						newSlides[e.target.closest('div').dataset.index].content = e.target.value;
						setAttributes({ slides: newSlides });
					};
					div.appendChild(content);

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

						const newOpens = [...opens];
						const [ removedOpen ] = newOpens.splice(oldIndex, 1);
						newOpens.splice(newIndex, 0, removedOpen);
						setOpens(newOpens);

						const newSlides = [...slides];
						const [ removedSlide ] = newSlides.splice(oldIndex, 1);
						newSlides.splice(newIndex, 0, removedSlide);
						setAttributes({ slides: newSlides });
					}
				});
			};

			return el('div',
				useBlockProps({
					className: 'iu-image-slider'
				}),
				'slider',
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
						el( components.BaseControl,
							{
								label: 'Slides'
							},
							attributes.slides.length ? el( 'div',
								{
									className: 'iu-image-slider-slides',
									ref: renderSlides
								}
							) : '',
							el( blockEditor.MediaUploadCheck,
								{},
								el( blockEditor.MediaUpload,
									{
										title: 'Select image',
										value: null,
										allowedTypes: ['image'],
										onSelect: function(img){
											const newSlide = {
												image: {
													id: img.id,
													url: img.url,
													thumb: img.mime == 'image/svg+xml' ? img.url : img.sizes.thumbnail.url
												},
												content: defaultContent
											};
											setAttributes({ slides: slides.concat(newSlide) });
											setOpens(opens.concat(true));
										},
										render: function(props){
											return el( components.Button,
												{
													className: 'editor-post-featured-image__toggle',
													onClick: props.open,
												},
												'Add slide'
											);
										}
									}
								)
							)
						),
						el( components.TextareaControl,
							{
								label: 'Params',
								value: attributes.params,
								onChange: function(newValue){
									setAttributes({ params: newValue });
								}
							}
						)
					)
				)
			);
		},
		save: function({ attributes }){
			const data = '{'+attributes.params.replaceAll('"', '&quot;').replace(/\s/g, '')+'}';
			const slideEls = [];

			attributes.slides.forEach(function(slide){
				slideEls.push(
					el( 'div',
						{},
						el( 'div',
							{
								className: 'iu-image-slider-wrap'
							},
							el( 'img',
								{
									src: slide.image.url
								}
							),
							el( 'div',
								{
									className: 'iu-image-slider-content',
									dangerouslySetInnerHTML: {
										__html: slide.content
									}
								}
							)
						)
					)
				);
			});

			return el( 'div',
				{
					className: 'iu-image-slider'
				},
				el( 'div',
					{
						className: 'iu-image-slider-slider',
						'data-slick': data
					},
					...slideEls
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