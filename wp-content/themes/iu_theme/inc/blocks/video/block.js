(function(blocks, element, blockEditor, components, serverSideRender){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/video', {
		edit: function({ setAttributes, attributes}) {
			let embedUrl = '';
			if (attributes.url !== ''){
				const videoId = Object.fromEntries(new URLSearchParams(attributes.url.split('?')[1]))['v'];
				if (videoId && videoId !== ''){
					embedUrl = 'https://youtube.com/embed/'+videoId;
				}
			}

			return el('div', 
				useBlockProps(),
				el(	components.Disabled,
					{},
					el( serverSideRender,
						{
							block: 'iu/video',
							attributes: attributes
						}
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
								label: 'Url',
								value: attributes.url,
								onChange: function(newValue){
									setAttributes({ url: newValue });
								}
							}
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
	window.wp.components,
	window.wp.serverSideRender
);