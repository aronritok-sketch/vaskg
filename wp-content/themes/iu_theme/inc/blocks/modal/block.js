(function(blocks, element, blockEditor, components){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/modal', {
		edit: function({ setAttributes, attributes}) {
			return el('div', 
				useBlockProps({
					className: 'iu-modal'
				}),
				'Modal: '+attributes.id,
				el( blockEditor.InnerBlocks, 
					{}
				),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
						el( components.TextControl,
							{
								label: 'ID',
								value: attributes.id,
								onChange: function(newValue){
									setAttributes({ id: newValue });
								}
							}
						)
					)
				)
			);
		},
		save: function({attributes}){
			return el('div',
				{
					className: 'iu-modal',
					'id': attributes.id
				}, 
				el('div',
					{
						className: 'iu-modal-dialog'
					},
					el('a',
						{
							className: 'iu-modal-close'
						},
						el('span',
							{
								className: 'dashicons dashicons-no-alt'
							}
						)
					),
					el('div',
						{
							className: 'iu-modal-content'
						},
						el( blockEditor.InnerBlocks.Content, {} )
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