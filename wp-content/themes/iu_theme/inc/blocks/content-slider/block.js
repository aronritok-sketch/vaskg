(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/content-slider', {
		edit: function({ setAttributes, attributes, clientId }){
			const classNames = ['iu-content-slider'];
			if (attributes.className) classNames.push(attributes.className);

			return el( 'div',
				useBlockProps({
					className: classNames.join(' ')
				}),
				el( blockEditor.InnerBlocks, 
					{ 
						allowedBlocks: [ 'iu/slide' ],
						template: [ [ 'iu/slide' ] ]
					}
				),
				el( 'style',
					{},
					`#block-`+clientId+` .wp-block-iu-slide {
						display: none;
					}
					#block-`+clientId+`:not(:has(.is-selected)) .wp-block-iu-slide:first-child, #block-`+clientId+`:has(.is-selected) .wp-block-iu-slide.is-selected, #block-`+clientId+`:has(.is-selected) .wp-block-iu-slide:has(.is-selected) {
						display: block;
					}`
				),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
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
			const classNames = [ 'iu-content-slider' ];
			if (attributes.className) classNames.push(attributes.className);

			return el( 'div',
				{
					className: classNames.join(' ')
				},
				el( 'div',
					{
						'data-slick': '{'+attributes.params+'}',
						className: 'iu-content-slider-slider'
					},
					el( blockEditor.InnerBlocks.Content, {} )
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