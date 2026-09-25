(function(blocks, element, blockEditor, components, data){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/template', {
		edit: function({ clientId }){
			const { records, hasResolved } = wp.coreData.useEntityRecords('postType', 'iu_template', { per_page: -1 });

			const { setBlockEditingMode } = data.useDispatch('core/block-editor');
			const setBlockEditMode = function( setEditMode, blocks ){
				blocks.forEach(function(block){
					if (block.name === 'iu/content') return;
					setEditMode( block.clientId, 'disabled' );
					setBlockEditMode( setEditMode, block.innerBlocks );
				});
			}

			const innerBlocks = data.useSelect(function(select){
				return select('core/block-editor').getBlocks(clientId);
			}, [ clientId ]);

			const { replaceBlock, replaceInnerBlocks, updateBlockListSettings } = wp.data.useDispatch('core/block-editor');

			const frontPageId = wp.coreData.useEntityProp('root', 'site', 'page_on_front')[0];

			wp.element.useEffect(function(){
				if (hasResolved){
					const currentPostType = wp.data.select('core/editor').getCurrentPostType();
					const currentPostId = wp.data.select("core/editor").getCurrentPostId();
					
					const record = records.find(function(item){
						if (currentPostId == frontPageId) {
							return item.meta.iu_template_position === 'content' && item.meta.iu_template_type === 'front_page';
						}
						return item.meta.iu_template_position === 'content' && item.meta.iu_template_type === 'single_'+currentPostType;
					});

					if (!record) return;

					const blocks = wp.blocks.parse(record.content.raw);
					const findContentBlock = function(blocks){
						let b = false;
						for (const block of blocks){
							if (block.name == 'iu/content') return block;
							b = findContentBlock(block.innerBlocks);
							if (b) return b;
						}
						return false;
					}
					
					const contentBlock = findContentBlock(blocks);
					
					replaceInnerBlocks(clientId, blocks);
					//setBlockEditMode(setBlockEditingMode, blocks);
					replaceInnerBlocks(contentBlock.clientId, innerBlocks);
				}
			}, [hasResolved]);

			const blockProps = useBlockProps();
			const innerBlocksProps = blockEditor.useInnerBlocksProps(blockProps);

			return el('div',
				blockProps,
				!hasResolved ? el(wp.components.Spinner) :
				innerBlocksProps.children
			);
		},
		save: function(){
			return el(wp.element.Fragment,
				{},
				el(wp.blockEditor.InnerBlocks.Content, {})
			);
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.data
);