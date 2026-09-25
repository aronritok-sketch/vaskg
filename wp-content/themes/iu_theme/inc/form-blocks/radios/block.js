(function(blocks, element, blockEditor, components){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/form-radios', {
		edit: function({ setAttributes, attributes}) {
			let classNames = ['iu-form-field', 'iu-form-radios'];
			
			if (attributes.validate.includes('required|')){
				classNames.push('iu-form-field-required');
			}

			let options = [];
			if (attributes.options != '') {
				attributes.options.split("\n").forEach(function(item){
					const op = item.split('|');
					options.push( 
						el( 'div',
							{
								className: 'iu-form-radio-wrap'
							},
							el('span',
								{
									className: 'iu-form-radio-input'
								},
								el('input',
									{
										type: 'radio'
									}
								)
							),
							el('span',
								{
									className: 'iu-form-radio-label'
								},
								op.length > 1 ? op[1] : op[0]
							)
						)
					);
				});
			}

			return el('div', 
				useBlockProps({
					className: classNames.join(' ')
				}),
				attributes.label != '' ? el('label',
					{},
					attributes.label
				) : '',
				...options,
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
						el( components.TextareaControl,
							{
								label: 'Options',
								value: attributes.options,
								onChange: function(newValue){
									setAttributes({ options: newValue });
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
			let classNames = ['iu-form-field', 'iu-form-radios'];

			let options = [];
			if (attributes.options != '') {
				attributes.options.split("\n").forEach(function(item, index){
					const op = item.split('|');
					options.push( 
						el( 'div',
							{
								className: 'iu-form-radio-wrap'
							},
							el('span',
								{
									className: 'iu-form-radio-input'
								},
								el('input',
									{
										type: 'radio',
										name: attributes.name,
										value: op[0]
									}
								)
							),
							el('span',
								{
									className: 'iu-form-radio-label'
								},
								op.length > 1 ? op[1] : op[0]
							)
						)
					);
				});
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
				...options,
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