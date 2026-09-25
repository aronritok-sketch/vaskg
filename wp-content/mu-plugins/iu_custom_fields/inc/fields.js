(function(wp){
	if (iucf_custom_fields.length == 0) return;

	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editor;
	const el = wp.element.createElement;

	const createTextControl = function(attr, args, value, editPost){
		return el(wp.components.TextControl,
			{
				label: args.label,
				value: value || '',
				onChange: function(newValue){
					editPost({ meta: { [attr]: newValue } });
				}
			}
		);
	};

	const createTextareaControl = function(attr, args, value, editPost){
		return el(wp.components.TextareaControl,
			{
				label: args.label,
				value: value || '',
				onChange: function(newValue){
					editPost({ meta: { [attr]: newValue } });
				}
			}
		);
	};

	const createSelectControl = function(attr, args, value, editPost){
		if (!value) editPost({ meta: { [attr]: args.options[0].value } });
		
		return el(wp.components.SelectControl,
			{
				label: args.label,
				value: value || args.options[0].value,
				options: args.options,
				onChange: function(newValue){
					editPost({ meta: { [attr]: newValue } });
				}
			}
		);
	};

	const createDatetimeControl = function(attr, args, value, editPost){
		const [isOpen, setOpen] = wp.element.useState(false);
		const [date, setDate] = wp.element.useState(value);

		return el(wp.components.BaseControl,
			{
				label: args.label
			},
			el('div',
				{
					style: {
						display: 'flex',
						justifyContent: 'space-between'
					}
				},
				el(wp.components.__experimentalText,
					{
						variant: 'muted'
					},
					value === null ? '-' : value
				),
				el(wp.components.Button,
					{
						variant: 'link',
						text: 'Select datetime',
						className: 'iu-select-datetime',
						onClick: function(e){
							if (e.target.tagName === 'BUTTON' && e.target.classList.contains('iu-select-datetime')) setOpen(true);
						}
					},
					!isOpen ? '' : 
					el(wp.components.Popover,
						{
							noArrow: false,
							onFocusOutside: function(){
								setOpen(false);
							}
						},
						el('div',
							{
								style: {
									padding: '15px'
								}
							},
							el(wp.components.DateTimePicker,
								{
									currentDate: date === null ? null : new Date(date),
									dateOrder: 'ymd',
									onChange: function(newValue){
										setDate(newValue.replace('T', ' ').replace(':00', ''));
									}
								}
							),
							el(wp.components.Button,
								{
									style: {
										display: 'inline-block',
										marginTop: '15px'
									},
									variant: 'primary',
									onClick: function(){
										editPost({ meta: { [attr]: date } });
										setOpen(false);
									}
								},
								'Select datetime'
							)
						)
					)
				)
			)
		);
	};

	const createMediaUpload = function(attr, args, value, editPost){
		return el( wp.components.BaseControl,
			{
				label: args.label
			},
			el( wp.blockEditor.MediaUpload,
				{
					onSelect: function(media){
						editPost({ meta: { [attr]: media.id+'|'+media.filename } });
					},
					render: function({ open }){
						return !value ? el( wp.components.Button,
							{
								variant: 'secondary',
								onClick: open
							},
							args.button_label
						) : el( wp.element.Fragment,
							{},
							el( 'div',
								{
									style: {
										padding: '9px 0'
									}
								},
								el( wp.components.Dashicon, { icon: 'media-default' } ), 
								value.split('|')[1]
							),
							el( wp.components.Button,
								{
									className: 'is-destructive',
									variant: 'link',
									onClick: function(){
										editPost({ meta: { [attr]: null } });
									}
								},
								args.remove_label
							)
						);
					}
				}
			)
		);

	};

	registerPlugin('iucf', {
		render: function(props){

			const meta = wp.data.useSelect(function(select){
				return select('core/editor').getEditedPostAttribute('meta');
			});
			
			const { editPost } = wp.data.useDispatch('core/editor');

			let panels = [];
			Object.entries(iucf_custom_fields).forEach(function([panel, args]){
				let fields = [];
				Object.entries(args.fields).forEach(function([name, field]){
					switch(field.type){
						case 'text': fields.push(createTextControl(name, field, meta[name], editPost));
							break;
						case 'textarea': fields.push(createTextareaControl(name, field, meta[name], editPost));
							break;
						case 'select': fields.push(createSelectControl(name, field, meta[name], editPost));
							break;
						case 'datetime': fields.push(createDatetimeControl(name, field, meta[name], editPost)); 
							break;
						case 'file': fields.push(createMediaUpload(name, field, meta[name], editPost));
							break;
						case 'callback': {
							eval('var fn = '+field.render+';');
							fields.push(fn.apply(this, [meta]));
						}
					}
				});
				panels.push(
					el(PluginDocumentSettingPanel,
						{
							name: panel,
							title: args.label,
							class: (panels.length == 0 ? 'is-opened' : '')
						},
						...fields
					)
				);
			});

			return el(wp.element.Fragment,
				{},
				...panels
			);
		}
	});

})(window.wp);