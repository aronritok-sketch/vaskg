(function(blocks, element, blockEditor, components, serverSideRender){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/post-navigation', {
		edit: function({ setAttributes, attributes}) {
			return el( 'div', 
				useBlockProps({
					className: 'iu-post-navigation'
				}),
				el( 'a',
					{
						className: 'prev'
					},
					'← [[Previous post title]]'
				),
				el( 'a',
					{
						className: 'next'
					},
					'[[Next post title]] →'
				),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
						el( components.TextareaControl,
							{
								label: 'Query params',
								value: attributes.params,
								onChange: function(newValue){
									setAttributes({ params: newValue });
								}
							}
						),
						el( components.TextControl,
							{
								label: 'Previous text',
								value: attributes.previous,
								onChange: function(newValue){
									setAttributes({ previous: newValue });
								}
							}
						),
						el( components.TextControl,
							{
								label: 'Next text',
								value: attributes.next,
								onChange: function(newValue){
									setAttributes({ next: newValue });
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