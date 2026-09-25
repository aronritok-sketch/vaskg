(function(blocks, element, blockEditor, components){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/form-steps', {
		edit: function({ setAttributes, attributes}) {
			let classNames = ['iu-form-steps'];

			let steps = [];
			attributes.steps.split("\n").forEach(function(step, index){
				steps.push(el('div',
						{
							className: 'iu-form-steps-step'+(index === 0 ? ' active' : '')
						},
						el('div',
							{
								className: 'iu-form-steps-step-wrap'
							},
							el('span',
								{
									className: 'iu-form-steps-step-index'
								},
								index+1
							),
							el('span',
								{
									className: 'iu-form-steps-step-label'
								},
								step
							)
						)
					)
				);
			});

			return el('div', 
				useBlockProps({
					className: classNames.join(' ')
				}),
				...steps,
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody,
						{
							title: 'Settings',
						},
						el( components.TextareaControl,
							{
								label: 'Steps',
								value: attributes.steps,
								onChange: function(newValue){
									setAttributes({ steps: newValue });
								}
							}
						)
					)
				)
			);
		},
		save: function({attributes}){
			let classNames = ['iu-form-steps'];

			let steps = [];
			attributes.steps.split("\n").forEach(function(step, index){
				steps.push(el('div',
						{
							className: 'iu-form-steps-step'+(index === 0 ? ' active' : '')
						},
						el('div',
							{
								className: 'iu-form-steps-step-wrap'
							},
							el('span',
								{
									className: 'iu-form-steps-step-index'
								},
								index+1
							),
							el('span',
								{
									className: 'iu-form-steps-step-label'
								},
								step
							)
						)
					)
				);
			});

			return el('div',
				{
					className: classNames.join(' ')
				},
				...steps
			);
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components
);