(function(blocks, element, blockEditor, components){
	const el = element.createElement;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'iu/content', {
		edit: function({ setAttributes, attributes }){
			const currentPostType = wp.data.select('core/editor').getCurrentPostType();

			const blockProps = useBlockProps({
				className: 'iu-content'
			});
			const innerBlocksProps = blockEditor.useInnerBlocksProps(blockProps);

			return el('div',
				{},
				currentPostType !== 'iu_template' ?
				el(blockEditor.InnerBlocks, {})
				:
				el('div',
					blockProps,
					el( 'h2',
						{
							className: 'iu-heading' 
						},
						'[[Post content heading]]'
					),
					el( 'p',
						{
							className: 'iu-text' 
						},
						'[[Post content paragraph text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aenean vel convallis tortor, quis tempor sem. Praesent tincidunt lectus scelerisque, scelerisque erat non, ultrices mi. Sed consectetur vestibulum dui quis hendrerit. Integer in pellentesque ipsum, non efficitur magna.]]'
					),
					el( 'h3',
						{
							className: 'iu-heading' 
						},
						'[[Post content heading]]'
					),
					el( 'p',
						{
							className: 'iu-text' 
						},
						'[[Integer ullamcorper elementum orci ut feugiat. Vestibulum augue enim, lacinia sit amet pulvinar ac, posuere et felis. In hac habitasse platea dictumst. Fusce massa turpis, commodo a finibus sit amet, lacinia ut elit. Fusce pretium vitae dolor nec porttitor.]]'
					),
					el( 'h4',
						{
							className: 'iu-heading' 
						},
						'[[Post content heading]]'
					),
					el( 'p',
						{
							className: 'iu-text' 
						},
						'[[Nam sagittis condimentum rhoncus. Donec sagittis laoreet metus at vulputate. Cras euismod sagittis metus, nec venenatis elit porttitor at. Nam turpis magna, mollis ut libero vitae, porta mollis neque. ]]'
					)
				)
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
	window.wp.components
);