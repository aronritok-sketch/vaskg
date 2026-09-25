(function(blocks, element, blockEditor, components){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/backtotop', {
		edit: function({ setAttributes, attributes}) {
			return el('div', 
				useBlockProps({
					className: 'iu-backtotop'
				}),
				attributes.text,
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
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
		save: function({attributes}){
			return el('a',
				{
					className: 'iu-backtotop',
					onclick: 'window.scrollTo({top: 0, behavior: "smooth"})'
				}, 
				attributes.text
			);
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components
);