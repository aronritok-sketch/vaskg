(function(blocks, element, blockEditor, components, data){
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps;

	blocks.registerBlockType( 'iu/row', {
		edit: function({ setAttributes, attributes, clientId }) {
			var structure = attributes.columns.split('|');

			var blocks = data.useSelect(function(select){
				return select("core/block-editor").getBlocks(clientId);
			});

			const { replaceInnerBlocks, moveBlocksToPosition, updateBlockAttributes } = data.useDispatch('core/block-editor');

			React.useEffect(function(){
				structure.forEach(function(col, index){
					if (typeof blocks[index] != 'undefined'){
						updateBlockAttributes(blocks[index].clientId, { width: col });
					}
				});

				if (structure.length < blocks.length){
					var childClientId = blocks[structure.length-1].clientId;
					var index = data.select("core/block-editor").getBlocks(childClientId).length;

					for(var i = structure.length; i < blocks.length; i++){
						var childClientIds = data.select("core/block-editor").getBlockOrder(blocks[i].clientId);
						if (childClientIds.length > 0){
							moveBlocksToPosition(childClientIds, blocks[i].clientId, childClientId, index);
						}
					}				
					
					var new_blocks = data.select("core/block-editor").getBlocks(clientId).slice(0, structure.length);
					replaceInnerBlocks(clientId, new_blocks, false);
				}

				if (structure.length > blocks.length){
					var inner_blocks = [ ...blocks ];

					for(var i = blocks.length; i < structure.length; i++){
						inner_blocks.push(wp.blocks.createBlock("iu/column", { width: structure[i] }));
					}
					replaceInnerBlocks(clientId, inner_blocks, false);
				}
			}, [ structure, blocks, updateBlockAttributes ]);

			let classNames = [ 'iu-row' ];
			if (attributes.vertical !== '') classNames.push('iu-row-vertical-'+attributes.vertical);
			if (attributes.bgImage) classNames.push('iu-has-background');

			if (attributes.className) classNames.push(attributes.className);

			return el( 'div',
				useBlockProps({
					className: classNames.join(' ')
				}),
				el( blockEditor.InnerBlocks, 
					{
						allowedBlocks: [ 'iu/column' ],
						renderAppender: false
					}
				),
				el( blockEditor.InspectorControls,
					{},
					el( components.PanelBody, 
						{
							title: 'Settings'
						},
						el( components.SelectControl,
							{
								label: 'Columns',
								value: attributes.columns,
								options: [
									{ label: '100%', value: '1-1' },
									{ label: '50% - 50%', value: '1-2|1-2' },
									{ label: '33% - 33% - 33%', value: '1-3|1-3|1-3' },
									{ label: '66% - 33%', value: '2-3|1-3' },
									{ label: '33% - 66%', value: '1-3|2-3' },
									{ label: '75% - 25%', value: '3-4|1-4' },
									{ label: '25% - 75%', value: '1-4|3-4' },
									{ label: '25% - 25% - 25% - 25%', value: '1-4|1-4|1-4|1-4' }
								],
								onChange: function(newValue){
									setAttributes({ columns: newValue });
								}
							}
						),
						el( components.SelectControl,
							{
								label: 'Vertical',
								value: attributes.vertical,
								options: [
									{ label: 'Top', value: '' },
									{ label: 'Center', value: 'center' },
									{ label: 'Bottom', value: 'flex-end' }
								],
								onChange: function(newValue){
									setAttributes({ vertical: newValue });
								}
							}
						)
					)
				)
			);
		},
		save: function({ attributes }){
			let classNames = [ 'iu-row' ];
			if (attributes.vertical !== '') classNames.push('iu-row-vertical-'+attributes.vertical);
			if (attributes.bgImage) classNames.push('iu-has-background');

			if (attributes.className) classNames.push(attributes.className);

			return el( 'div',
				{
					className: classNames.join(' ')
				},
				el( blockEditor.InnerBlocks.Content, {} )
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