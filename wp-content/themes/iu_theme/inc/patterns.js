const IUConvertPatternButton = ({ onClose, selectedClientIds }) => {
	const el = wp.element.createElement;
	
	const [ isModalOpen, setIsModalOpen ] = wp.element.useState(false);
	const [ title, setTitle ] = wp.element.useState('');
	const [ isSynced, setIsSynced ] = wp.element.useState(true);

	const canConvert = wp.data.useSelect(function(select){
		const { getBlocksByClientId } = select('core/block-editor');
		const blocks = getBlocksByClientId(selectedClientIds) ?? [];
		
		return blocks.length === 1 && blocks[0] && blocks[0].name !== 'iu/pattern';
	});

	if (!canConvert){
		return null;
	}

	const { saveEntityRecord } = wp.data.useDispatch('core');
	const { createBlock } = wp.blocks;
	const { replaceBlock } = wp.data.useDispatch('core/block-editor');
	const blocks = wp.data.useSelect(function(select){
		return select('core/block-editor').getBlocksByClientId(selectedClientIds);
	});

	const onCreate = wp.element.useCallback(async function(title, isSynced){
		const meta = isSynced ? {
			iu_pattern_synced: 1
		} : undefined;

		const pattern = {
			title: title,
			content: wp.blocks.serialize(
				blocks
			),
			status: 'publish',
			meta
		};

		const newRecord = await saveEntityRecord('postType', 'iu_pattern', pattern);
		wp.blocks.registerBlockVariation('iu/pattern',{
		    name: newRecord.name,
		    title: title,
		    category: 'patterns',
		    attributes: { ref: newRecord.id }
		});

		
		if (isSynced){
			const block = wp.blocks.createBlock('iu/pattern',{
				ref: newRecord.id
			});
			replaceBlock(blocks[0].clientId, block);
		}
	},[selectedClientIds, createBlock]);

	return el( wp.element.Fragment,
		{}, 
		el( wp.components.MenuItem,
			{
				onClick: function(){
					setIsModalOpen(true);
				}
			},
			'Create pattern'
		),
		isModalOpen ? el(wp.components.Modal,
			{
				title: 'Create pattern',
				overlayClassName: 'iu-pattern-create-button-modal',
				onRequestClose: function(){
					setIsModalOpen(false);
					setTitle('');
				}
			},
			el( 'form',
				{
					onSubmit: function(event){
						event.preventDefault();
						onCreate(title, isSynced);
						onClose();
					}
				},
				el( 'div',
					{
						style: {
							display: 'flex',
							flexDirection: 'column',
							gap: '15px'
						}
					},
					el( wp.components.TextControl,
						{
							label: 'Name',
							value: title,
							onChange: function(newValue){
								setTitle(newValue);
							}
						}
					),
					el( wp.components.ToggleControl,
						{
							label: 'Synced',
							checked: isSynced,
							onChange: function(newValue){
								setIsSynced(newValue);
							},
							style: {
								marginTop: '15px'
							}
						}
					),
					el( 'div',
						{
							style: {
								display: 'flex',
								justifyContent: 'flex-end',
								gap: '10px'
							}
						},
						el( wp.components.Button,
							{
								variant: 'tertiary',
								onClick: function(){
									setIsModalOpen(false);
								}
							},
							'Cancel'
						),
						el( wp.components.Button,
							{
								variant: 'primary',
								type: 'submit'
							},
							'Create'
						)
					)
				)
			)
		) : ''
	);
};

const addBlockSettingsMenuItems = wp.compose.createHigherOrderComponent(function(BlockEdit){
	return function(props){
		const el = wp.element.createElement;

		return el( wp.element.Fragment,
			{},
			el( BlockEdit, { ...props }),
			el( wp.blockEditor.BlockControls,
				{},
				el( wp.blockEditor.BlockSettingsMenuControls,
					{
						children: function({ onClose, selectedClientIds }){
							return el( IUConvertPatternButton,
								{
									onClose: onClose,
									selectedClientIds: selectedClientIds
								}
							);
						}
					}
				)
			)
		);		
	}
}, 'addBlockSettingsMenuItems');
wp.hooks.addFilter('editor.BlockEdit', 'iu', addBlockSettingsMenuItems);