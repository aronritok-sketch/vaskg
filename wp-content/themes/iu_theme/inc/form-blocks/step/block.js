(function(blocks, element, blockEditor, components){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/form-step', {
		edit: function({ setAttributes, attributes}) {
			let classNames = ['iu-form-step'];

			return el('div', 
				useBlockProps({
					className: classNames.join(' ')
				}),
				attributes.label !== '' ? el('h3', { classNames: 'iu-heading' }, attributes.label ) : '', 
				el('div',
					{
						className: 'iu-form-step-fields'
					},
					el( blockEditor.InnerBlocks, {}),
				),
				el('div',
					{
						className: 'iu-form-step-foot'
					},
					attributes.prev !== '' ? el('a',
						{
							className: 'iu-form-step-nav iu-form-step-prev iu-button iu-button-default'
						},
						el('span',
							{
								className: 'iu-button-text'
							},
							el('span',
								{

								},
								attributes.prev
							)
						)
					) : '',
					attributes.next !== '' ? el('a',
						{
							className: 'iu-form-step-nav iu-form-step-next iu-button iu-button-default'
						},
						el('span',
							{
								className: 'iu-button-text'
							},
							el('span',
								{

								},
								attributes.next
							)
						)
					) : ''
				),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
						el( components.TextControl,
							{
								label: 'Label',
								value: attributes.label,
								onChange: function(newValue){
									setAttributes({ label: newValue });
								}
							}
						),
						el( components.TextControl,
							{
								label: 'Prev',
								value: attributes.prev,
								onChange: function(newValue){
									setAttributes({ prev: newValue });
								}
							}
						),
						el( components.TextControl,
							{
								label: 'Next',
								value: attributes.next,
								onChange: function(newValue){
									setAttributes({ next: newValue });
								}
							}
						)
					)
				)
			);
		},
		save: function({attributes}){
			let classNames = ['iu-form-step'];

			return el('div',
				{
					className: classNames.join(' ')
				},
				attributes.label !== '' ? el('h3', { classNames: 'iu-heading' }, attributes.label ) : '',
				el('div',
					{
						className: 'iu-form-step-fields'
					},
					el( blockEditor.InnerBlocks.Content, {})
				),
				el('div',
					{
						className: 'iu-form-step-foot'
					},
					attributes.prev !== '' ? el('a',
						{
							className: 'iu-form-step-nav iu-form-step-prev iu-button iu-button-default'
						},
						el('span',
							{
								className: 'iu-button-text'
							},
							el('span',
								{

								},
								attributes.prev
							)
						)
					) : '',
					attributes.next !== '' ? el('a',
						{
							className: 'iu-form-step-nav iu-form-step-next iu-button iu-button-default'
						},
						el('span',
							{
								className: 'iu-button-text'
							},
							el('span',
								{

								},
								attributes.next
							)
						)
					) : ''
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