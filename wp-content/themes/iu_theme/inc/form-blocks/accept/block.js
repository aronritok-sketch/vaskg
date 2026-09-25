(function(blocks, element, blockEditor, components){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/form-accept', {
		edit: function({ setAttributes, attributes}) {
			let classNames = ['iu-form-field', 'iu-form-accept', 'iu-form-field-required'];

			return el('div', 
				useBlockProps({
					className: classNames.join(' ')
				}),
				el('div',
					{
						className: 'iu-form-checkbox-wap'
					},
					el('span',
						{
							className: 'iu-form-checkbox-input'
						},
						el('input',
							{
								name: attributes.name,
								type: 'checkbox'
							}
						)
					),
					el('span',
						{
							className: 'iu-form-checkbox-label',
							dangerouslySetInnerHTML: {
								__html: attributes.label
							}
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
								label: 'Name',
								value: attributes.name,
								onChange: function(newValue){
									setAttributes({ name: newValue });
								}
							}
						),
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
								label: 'Error message',
								value: attributes.error,
								onChange: function(newValue){
									setAttributes({ error: newValue });
								}
							}
						)
					)
				)
			);
		},
		save: function({attributes}){
			let classNames = ['iu-form-field', 'iu-form-accept', 'iu-form-field-required'];

			return el('div',
				{
					className: classNames.join(' '),
					'data-validate': attributes.error != '' ? 'required|'+attributes.error : null
				},
				el('div',
					{
						className: 'iu-form-checkbox-wrap'
					},
					el('span',
						{
							className: 'iu-form-checkbox-input'
						},
						el('input',
							{
								name: attributes.name,
								type: 'checkbox'
							}
						)
					),
					el('span',
						{
							className: 'iu-form-checkbox-label',
							dangerouslySetInnerHTML: {
								__html: attributes.label
							}
						}
					)
				),
				attributes.error != '' ? el('span',
					{	
						className: 'iu-form-field-error-message'
					}
				) : ''
			);
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components
);