(function(blocks, element, blockEditor, components, data, serverSideRender){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/pattern', {
		edit: function({ clientId, setAttributes, attributes}) {
			const { record, hasResolved } = wp.coreData.useEntityRecord('postType', 'iu_pattern', attributes.ref);
			const [ blocks ] = wp.coreData.useEntityBlockEditor('postType', 'iu_pattern', { id: attributes.ref });

			const blockProps = useBlockProps();
			const innerBlocksProps = blockEditor.useInnerBlocksProps(blockProps, {
				templateLock: 'all',
				value: blocks,
				renderAppender: undefined,
				onInput: () => {},
				onChange: () => {}
			});

			const { replaceBlock } = wp.data.useDispatch('core/block-editor');
			if (record && !record.meta.iu_pattern_synced){
				replaceBlock(clientId, blocks);
			}

			const { setBlockEditingMode } = data.useDispatch('core/block-editor');
			const setBlockEditMode = function( setEditMode, blocks ){
				blocks.forEach(function(block){
					setEditMode( block.clientId, 'disabled' );
					setBlockEditMode( setEditMode, block.innerBlocks );
				});
			}

			const { innerBlocks, onNavigateToEntityRecord } = data.useSelect(function(select){
				const { getBlocks, getSettings, getBlockEditingMode } = select('core/block-editor');
				return {
					innerBlocks: getBlocks(clientId),
					onNavigateToEntityRecord: getSettings().onNavigateToEntityRecord
				};
			}, [ clientId ]);

			wp.element.useEffect(function(){
				setBlockEditMode(setBlockEditingMode, innerBlocks);
			}, [innerBlocks, setBlockEditingMode]);

			return el( 'div',
				blockProps,
				innerBlocksProps.children,
				el( blockEditor.BlockControls,
					{},
					el( components.ToolbarGroup,
						{},
						el( components.ToolbarButton,
							{
								onClick: function(){
									onNavigateToEntityRecord({
										postId: attributes.ref,
										postType: 'iu_pattern'
									});
								}
							},
							'Edit pattern'
						)
					),
					el( wp.blockEditor.BlockSettingsMenuControls,
						{
							children: function({ onClose, selectedClientIds }){
								return el( wp.components.MenuItem,
									{
										onClick: function(){
											replaceBlock(clientId, blocks);
											onClose();
										}
									},
									'Detach'
								);
							}
						}
					)
				)
			);
		}
	});

	iuPatterns.forEach(function(item){
		wp.blocks.registerBlockVariation('iu/pattern',{
		    name: item.name,
		    title: item.title,
		    category: 'patterns',
		    attributes: { ref: item.id }
		});
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.data,
	window.wp.serverSideRender
);