(function(blocks, element, blockEditor, components){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/form-text', {
		edit: function({ setAttributes, attributes}) {
			let classNames = ['iu-form-field', 'iu-form-text'];
			
			if (attributes.validate.includes('required|')){
				classNames.push('iu-form-field-required');
			}

			return el('div', 
				useBlockProps({
					className: classNames.join(' ')
				}),
				attributes.label != '' ? el('label',
					{},
					attributes.label
				) : '',
				el('input',
					{
						type: 'text',
						placeholder: attributes.placeholder || null
					}
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
								label: 'Placeholder',
								value: attributes.placeholder,
								onChange: function(newValue){
									setAttributes({ placeholder: newValue });
								}
							}
						),
						el( components.TextareaControl,
							{
								label: 'Validation',
								value: attributes.validate,
								onChange: function(newValue){
									setAttributes({ validate: newValue });
								}
							}
						)
					)
				)
			);
		},
		save: function({attributes}){
			let classNames = ['iu-form-field', 'iu-form-text'];

			if (attributes.validate.includes('required|')){
				classNames.push('iu-form-field-required');
			}

			return el('div',
				{
					className: classNames.join(' '),
					'data-validate': attributes.validate != '' ? attributes.validate : null
				},
				attributes.label != '' ? el('label',
					{},
					attributes.label
				) : '',
				el('input',
					{
						type: 'text',
						name: attributes.name,
						placeholder: attributes.placeholder || null
					}
				),
				attributes.validate != '' ? el('span',
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