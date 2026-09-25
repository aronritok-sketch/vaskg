(function(blocks, element, blockEditor, components){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/form-select', {
		edit: function({ setAttributes, attributes}) {
			let classNames = ['iu-form-field', 'iu-form-select'];
			
			if (attributes.validate.includes('required|')){
				classNames.push('iu-form-field-required');
			}

			let options = [];
			if (attributes.options != ''){
				attributes.options.split("\n").forEach(function(item){
					const it = item.split('|');
					options.push( 
						el( 'option',
							{
								value: it[0],
								selected: it[0] === attributes.default ? true : null
							},
							it.length > 1 ? it[1] : it[0]
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
				el( 'div',
					{
						className: 'iu-form-select-wrap'
					},
					el('select',
						{},
						...options
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
						el( components.TextareaControl,
							{
								label: 'Options',
								value: attributes.options,
								onChange: function(newValue){
									setAttributes({ options: newValue });
								}
							}
						),
						el( components.TextControl,
							{
								label: 'Default',
								value: attributes.default,
								onChange: function(newValue){
									setAttributes({ default: newValue });
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
			let classNames = ['iu-form-field', 'iu-form-select'];

			if (attributes.validate.includes('required|')){
				classNames.push('iu-form-field-required');
			}

			let options = [];
			if (attributes.options != '') {
				attributes.options.split("\n").forEach(function(item){
					const it = item.split('|');
					options.push( 
						el( 'option',
							{
								value: it[0],
								selected: it[0] === attributes.default ? true : null
							},
							it.length > 1 ? it[1] : it[0]
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
				el( 'div',
					{
						className: 'iu-form-select-wrap'
					},
					el('select',
						{
							name: attributes.name
						},
						...options
					)
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