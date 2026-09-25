(function(iu_theme_form_blocks, blocks, element, blockEditor, components){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/form', {
		edit: function({ setAttributes, attributes}) {
			let classNames = [ 'iu-form' ];
			if (attributes.className) classNames.push(attributes.className);

			return el('div', 
				useBlockProps({
					className: classNames.join(' ')
				}),
				el( blockEditor.InnerBlocks, 
					{ 
						allowedBlocks: iu_theme_form_blocks
					}
				),
				el('div',
					{
						className: 'iu-form-foot iu-button-group'
					},
					el('a',
						{
							className: 'iu-form-submit iu-button iu-button-highlight'
						},
						el('span',
							{
								className: 'iu-button-text'
							},
							el('span',
								{

								},
								attributes.buttonLabel
							)
						)
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
								label: 'Form ID',
								value: attributes.formId,
								onChange: function(newValue){
									setAttributes({ formId: newValue });
								}
							}
						),
						el( components.TextControl,
							{
								label: 'Button label',
								value: attributes.buttonLabel,
								onChange: function(newValue){
									setAttributes({ buttonLabel: newValue });
								}
							}
						)
					),
					el( components.PanelBody,
						{
							title: 'Email',
							initialOpen: false
						},
						el( components.TextControl,
							{
								label: 'Email address',
								value: attributes.email,
								onChange: function(newValue){
									setAttributes({ email: newValue });
								}
							}
						),
						el( components.TextControl,
							{
								label: 'Subject',
								value: attributes.subject,
								onChange: function(newValue){
									setAttributes({ subject: newValue });
								}
							}
						),
						el( components.TextareaControl,
							{
								label: 'Content',
								value: attributes.message,
								onChange: function(newValue){
									setAttributes({ message: newValue });
								}
							}
						),
						el( components.TextControl,
							{
								label: 'From',
								value: attributes.from,
								onChange: function(newValue){
									setAttributes({ from: newValue });
								}
							}
						)
					),
					el( components.PanelBody,
						{
							title: 'Messages',
							initialOpen: false
						},
						el( components.TextControl,
							{
								label: 'Success',
								value: attributes.success,
								onChange: function(newValue){
									setAttributes({ success: newValue });
								}
							}
						)
					),
					el( components.PanelBody,
						{
							title: 'Active Campaigne',
							initialOpen: false
						},
						el( components.TextControl,
							{
								label: 'API URL',
								value: attributes.ac_url,
								onChange: function(newValue){
									setAttributes({ ac_url: newValue });
								}
							}
						),
						el( components.TextControl,
							{
								label: 'API Key',
								value: attributes.ac_key,
								onChange: function(newValue){
									setAttributes({ ac_key: newValue });
								}
							}
						),
						el( components.TextControl,
							{
								label: 'List ID',
								value: attributes.ac_listid,
								onChange: function(newValue){
									setAttributes({ ac_listid: newValue });
								}
							}
						)
					)
				)
			);
		},
		save: function({attributes}){
			let classNames = [ 'iu-form' ];
			if (attributes.className) classNames.push(attributes.className);

			return el('form',
				{
					className: classNames.join(' ')
				},
				el('input',
					{
						type: 'hidden',
						name: 'iu_form_id',
						value: attributes.formId
					}
				),
				el( blockEditor.InnerBlocks.Content, {}),
				el('div',
					{
						className: 'iu-form-foot iu-button-group',
					},
					el('a',
						{
							className: 'iu-form-submit iu-button iu-button-highlight'
						},
						el('span',
							{
								className: 'iu-button-text'
							},
							el('span',
								{

								},
								attributes.buttonLabel
							)
						)
					)
				),
				el('span',
					{
						className: 'iu-form-error-message'
					}
				),
				el('span',
					{
						className: 'iu-form-success-message'
					},
					attributes.success
				)
			);
		}
	});
})(
	iu_theme_form_blocks,
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components
);