(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/text-scroller', {
		edit: function({ setAttributes, attributes }){
			const classNames = ['iu-text-scroller'];

			const items = attributes.texts.split("\n").map(function(item){
				return el( 'div',
					{
						className: 'iu-text-scroller-item'
					},
					item
				);
			});

			return el( 'div',
				useBlockProps({
					className: classNames.join(' ')
				}),
				...items,
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
						el( components.TextareaControl,
							{
								label: 'Texts',
								value: attributes.texts,
								onChange: function(newValue){
									setAttributes({ texts: newValue });
								}
							}
						)
					)
				)
			);
		},
		save: function({ attributes }){
			const classNames = ['iu-text-scroller'];

			const items = attributes.texts.split("\n").map(function(item){
				return el( 'div',
					{
						className: 'iu-text-scroller-item'
					},
					item
				);
			});

			return el( 'div',
				{
					className: classNames.join(' ')
				},
				el( 'div',
					{
						className: 'iu-text-scroller-items-wrap'
					},
					el( 'div',
						{
							className: 'iu-text-scroller-items'
						},
						...items
					),
					el( 'div',
						{
							className: 'iu-text-scroller-items'
						},
						...items
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