const parseShortcodes = wp.compose.createHigherOrderComponent(function(BlockEdit){
	return wp.data.withSelect(function(select){
		return {
			meta: wp.data.select( 'core/editor' ).getEditedPostAttribute('meta')
		};
	})(function(props){
		if (!iu_shortcode_js) return wp.element.createElement(BlockEdit, {...props});
		if (props.name !== 'core/paragraph') return wp.element.createElement(BlockEdit, {...props});
		
		const currentPostType = wp.data.select('core/editor').getCurrentPostType();
		const [content, setContent] = wp.element.useState(props.attributes.content.originalHTML);

		wp.element.useEffect(function(){
			if (currentPostType === 'iu_template' || !content) return;

			const nameRegex = /\[(\w+)\s?(.*)\]/;
			const name = content.match(nameRegex);
				
			if (!name) return;

			let params = [];
			if (name[2] !== ""){
				params = wp.shortcode.attrs(name[2]).named;
			}

			if (iu_shortcode_js[name[1]]){
				props.setAttributes({ content: iu_shortcode_js[name[1]](props.meta, params) });
			}
		}, [props.meta]);

		return wp.element.createElement(BlockEdit, {...props});
	});
}, 'parseShortcodes');
wp.hooks.addFilter('editor.BlockEdit', 'iu', parseShortcodes);