(function(iucb_custom_blocks, blocks, element, blockEditor, components, serverSideRender){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	var createTextField = function(fieldName, field, value, setAttributes){
		eval('var eventFn = function(newValue){setAttributes({'+fieldName+':newValue})}');
		return el( components.TextControl, {
				label: field.label,
				value: value,
				onChange: eventFn
			}
		);
	};
	var createTextareaField = function(fieldName, field, value, setAttributes){
		eval('var eventFn = function(newValue){setAttributes({'+fieldName+':newValue})}');
		return el( components.TextareaControl, {
				label: field.label,
				value: value,
				onChange: eventFn
			}
		);
	};
	var createToggleField = function(fieldName, field, value, setAttributes){
		eval('var eventFn = function(newValue){setAttributes({'+fieldName+':newValue})}');
		return el( components.ToggleControl, {
			label: field.label,
			checked: value,
			onChange: eventFn
		});
	};
	var createSelectField = function(fieldName, field, value, setAttributes){
		eval('var eventFn = function(newValue){setAttributes({'+fieldName+':newValue})}');
		return el( components.SelectControl, {
			label: field.label,
			value: value,
			options: field.options,
			onChange: eventFn
		});
	};
	var createImageField = function(fieldName, field, value, setAttributes){
		eval('var eventFn = function(image){setAttributes({'+fieldName+': image.id})}');
		eval('var removeEventFn = function(){setAttributes({'+fieldName+': null})}');

		const image = wp.data.useSelect(function(select){
			return select('core').getMedia(value);
		});

		if (!value){
			return el( components.BaseControl,
				{
					label: field.label
				},
				el( blockEditor.MediaUploadCheck, 
					{},
					el( blockEditor.MediaUpload,
						{
							title: field.button_label,
							value: value,
							onSelect: eventFn,
							allowedTypes: ['image'],
							render: function(props){
								return el( components.Button,
									{
										className: 'editor-post-featured-image__toggle',
										onClick: props.open,
									},
									field.button_label || 'Select image'
								);
							}
						}
					)
				)
			);
		} else {
			return el( components.BaseControl,
				{
					label: field.label
				},
				el( blockEditor.MediaUploadCheck,
					{},
					el ( 'div',
						{},
						( !image ? el( components.Spinner, {}) :
							el( 'img',
								{
									src: image.mime_type == 'image/svg+xml' ? image.source_url : image.media_details.sizes.thumbnail.source_url
								}
							)
						),
					),
					el ( components.Button,
						{
							isLink: true,
							isDestructive: true,
							onClick: removeEventFn
						},
						field.remove_label || 'Remove image'
					)
				)
			);
		}
	};

	for(var name in iucb_custom_blocks){
		var args = {
			title: iucb_custom_blocks[name].title,
			description: iucb_custom_blocks[name].description,
			category: iucb_custom_blocks[name].category,
			icon: iucb_custom_blocks[name].icon,
			parent: iucb_custom_blocks[name].parent,
			attributess: iucb_custom_blocks[name].attributes,
			edit: function({ name, setAttributes, attributes }){

				const { uniqueName, anotherUniqueName } = wp.data.useSelect((select) => {
				    const { isSavingPost, isSavingNonPostEntityChanges } = select(wp.editor.store);
				    return {
				        uniqueName: isSavingPost(),
				        anotherUniqueName: isSavingNonPostEntityChanges(),
				    };
				});

				var panels = [blockEditor.InspectorControls, {}];
				for(var i in iucb_custom_blocks[name].fields){
					var panel = iucb_custom_blocks[name].fields[i];
					var fields = [components.PanelBody, { 
						title: panel.panel, 
						initialOpen: typeof panel.initialOpen != 'undefined' ? panel.initialOpen : true
					}];

					for(var fieldName in panel.fields){
						var field = panel.fields[fieldName];

						switch(field.type){
							case 'text': {
								fields.push(createTextField(fieldName, field, attributes[fieldName], setAttributes));
							} break;
							case 'textarea': {
								fields.push(createTextareaField(fieldName, field, attributes[fieldName], setAttributes));
							} break;
							case 'toggle': {
								fields.push(createToggleField(fieldName, field, attributes[fieldName], setAttributes));
							} break;
							case 'select': {
								fields.push(createSelectField(fieldName, field, attributes[fieldName], setAttributes));
							} break;
							case 'image': {
								fields.push(createImageField(fieldName, field, attributes[fieldName], setAttributes));
							} break;
						}
					}
					panels.push(el.apply(this, fields));
				}


				const editJS = iucb_custom_blocks[name].editJS ? (new Function('attributes','el',iucb_custom_blocks[name].editJS))(attributes, el) : null;

				return el('div',
					useBlockProps(),
					el.apply(this, panels),
					iucb_custom_blocks[name].editJS ? 
						typeof editJS !== 'string' ? editJS : el( 'div',
							{
								dangerouslySetInnerHTML: {
									__html: editJS
								}
							}
					) : el(	components.Disabled,
						{},
						//(uniqueName || anotherUniqueName) ? el(components.Spinner,{}) :
						el( serverSideRender,
							{
								block: name,
								attributes: attributes
							}
						)
					)
				)
			}
		};
		if (iucb_custom_blocks[name].saveJS){
			const blockName = name;
			args.save = function({attributes}){ return (new Function('attributes','el',iucb_custom_blocks[blockName].saveJS))(attributes, el) };
		}
		blocks.registerBlockType(name, { ...args, apiVersion: 3.0 });
	};

})(
	window.iucb_custom_blocks,
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.serverSideRender
);


	