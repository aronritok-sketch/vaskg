if (typeof iu == 'undefined'){
	var iu = {};
}

iu.blockUtils = {};

wp.hooks.addFilter(
    'blocks.getBlockDefaultClassName',
    'iu',
    function(className, blockName){
    	let blocks = {
    		'core/paragraph': 'iu-text',
    		'core/heading': 'iu-heading',
    		'core/list': 'iu-list',
    		'core/list-item': 'iu-list-item'
    	};
    	return blocks[blockName] ? className+' '+blocks[blockName] : className;
    }
);

const addInspectorPanels = wp.compose.createHigherOrderComponent(function(BlockEdit){
	return function(props){
		const supports = wp.data.useSelect(function(select){
			return select('core/blocks').getBlockSupport(props.name,'iu') || {};
	    });

	    let supportElements = [], style = {};
	    Object.keys(supports).forEach(function(key){
	    	if (!!supports[key] && iu.blockUtils[key]){
	    		supportElements.push(wp.element.createElement(iu.blockUtils[key].panel,props));
	    		if (iu.blockUtils[key].styles){
		    		style = {
		    			...style,
		    			...iu.blockUtils[key].styles(props.attributes)
		    		}
		    	}
	    	}
	    });

	    const selector = 'block-'+props.clientId;
	    const styleString = (
		  	Object.entries(style).map(([k, v]) => {
		  		k = k.replace(/[A-Z]/g, match => `-${match.toLowerCase()}`);
		  		return `${k}:${v}`
		  	}).join(';')
		);

	    React.useEffect(function(){
	    	if (styleString !== '') props.setAttributes({ iu_style: styleString });
	    },[ styleString ]);

		return wp.element.createElement( wp.element.Fragment,
			{},
			wp.element.createElement( BlockEdit, { ...props }),
			styleString === '' ? '' : wp.element.createElement('style', { id: selector+'_style' }, '#'+selector+'{'+styleString+'}'),
			!supportElements.length ? '' :
			wp.element.createElement( wp.blockEditor.InspectorControls,
				{ group: props.name === 'core/paragraph' ? 'styles' : 'settings' },
				...supportElements
			)
		);
	};
}, 'addInspectorPanels');
wp.hooks.addFilter('editor.BlockEdit', 'iu', addInspectorPanels);

wp.hooks.addFilter(
    'blocks.getSaveElement',
    'iu',
    function(element, blockType, attributes){
    	if (blockType.name === 'core/list-item'){
    		let classNames = !element.props.className ? [] : element.props.className.split(' ');
    		classNames.unshift('iu-list-item');
    		return wp.element.cloneElement(element, 
    			{
    				className: classNames.join(' ')
    			}
    		);
    	}
    	if (blockType.name === 'core/paragraph'){
    		let classNames = !element.props.className ? [] : element.props.className.replace('has-text-','iu-text-').split(' ');
    		classNames.unshift('iu-text');
    		return wp.element.cloneElement(element, 
	    		{
	    			className: classNames.join(' ')
	    		}
    		);
    	}
    	if (blockType.name === 'core/heading'){
	    	return wp.element.cloneElement(element, 
	    		{	
	    			className: !element.props.className ? '' : element.props.className.replace('has-text-','iu-text-')
	    		}
	    	);
	    }

	    return element;
    }
);

const addFormattingToParagraph = wp.compose.createHigherOrderComponent(function(BlockEdit){
	return function(props){
		if (props.name !== 'core/paragraph') return wp.element.createElement(BlockEdit, {...props});

		let classNames = props.attributes.className ? props.attributes.className.replace('iu-text-small','').replace('iu-text-muted','').trim().split(' ') : [];
		if (props.attributes.small){
			classNames.push('iu-text-small');
		}
		if (props.attributes.muted){
			classNames.push('iu-text-muted');
		}
		props.attributes.className = classNames.join(' ');

		const smallIcon = wp.element.createElement(wp.primitives.SVG,
			{
				width: '800px',
				height: '800px',
				viewBox: '0 0 16 16',
				xmlns: 'http://www.w3.org/2000/svg'
			},
			wp.element.createElement(wp.primitives.Path,
				{
					d: 'M3.36 7L1 13h1.34l.51-1.47h2.26L5.64 13H7L4.65 7H3.36zm-.15 3.53l.78-2.14.78 2.14H3.21zM11.82 4h-1.6L7 13h1.56l.75-2.29h3.36l.77 2.29H15l-3.18-9zM9.67 9.5l1.18-3.59c.059-.185.1-.376.12-.57.027.192.064.382.11.57l1.25 3.59H9.67z'
				}
			)
		);

		const mutedIcon = wp.element.createElement(wp.primitives.SVG,
			{
				width: '800px',
				height: '800px',
				viewBox: '0 0 24 24',
				xmlns: 'http://www.w3.org/2000/svg'
			},
			wp.element.createElement(wp.primitives.Path,
				{
					d: 'M17.75,14.500543 C18.9926407,14.500543 20,15.5079023 20,16.750543 L20,19.7503837 C20,20.9930243 18.9926407,22.0003837 17.75,22.0003837 L5.25,22.0003837 C4.00735931,22.0003837 3,20.9930243 3,19.7503837 L3,16.750543 C3,15.5079023 4.00735931,14.500543 5.25,14.500543 L17.75,14.500543 Z M17.75,16.000543 L5.25,16.000543 C4.83578644,16.000543 4.5,16.3363294 4.5,16.750543 L4.5,19.7503837 C4.5,20.1645972 4.83578644,20.5003837 5.25,20.5003837 L17.75,20.5003837 C18.1642136,20.5003837 18.5,20.1645972 18.5,19.7503837 L18.5,16.750543 C18.5,16.3363294 18.1642136,16.000543 17.75,16.000543 Z M7.05252224,11.9694299 L10.8061334,2.47427411 C11.0415216,1.87883471 11.8486376,1.84414583 12.1508398,2.36975012 L12.2013229,2.47486675 L15.9477118,11.9753527 C16.0996637,12.3606883 15.9104688,12.7962458 15.5251332,12.9481977 C15.171909,13.087487 14.7764843,12.9401182 14.5959915,12.6177829 L14.5522882,12.5256192 L13.556,10 L9.443,10 L8.44747776,12.5208817 C8.30788849,12.8739875 7.9301318,13.0620782 7.57143476,12.9736808 L7.47427411,12.9426336 C7.1211683,12.8030443 6.93307758,12.4252876 7.02147501,12.0665906 L7.05252224,11.9694299 L10.8061334,2.47427411 L7.05252224,11.9694299 Z M11.5027436,4.79226402 L10.036,8.5 L12.964,8.5 L11.5027436,4.79226402 Z'
				}
			)
		);

		return wp.element.createElement( wp.element.Fragment,
			{},
			wp.element.createElement( BlockEdit, { ...props }),
			wp.element.createElement( wp.blockEditor.BlockControls,
				{
					group: 'block'
				},
				wp.element.createElement( wp.components.Button,
					{
						icon: smallIcon,
						label: 'Small text',
						isPressed: props.attributes.small,
						onClick: function(){
							props.setAttributes({ small: !props.attributes.small });
						}
					}
				),
				wp.element.createElement( wp.components.Button,
					{
						icon: mutedIcon,
						label: 'Muted text',
						isPressed: props.attributes.muted,
						onClick: function(){
							props.setAttributes({ muted: !props.attributes.muted });
						}
					}
				),
			)
		)
	};
}, 'addFormattingToParagraph');
wp.hooks.addFilter('editor.BlockEdit', 'iu', addFormattingToParagraph);

const addAttributeAtts = function(settings, name){
	if (name !== 'core/paragraph') return settings;

	return {
		...settings,
		attributes: {
			...settings.attributes,
			atts: {
				type: 'string',
				default: ''
			}
		}
	};

};
wp.hooks.addFilter('blocks.registerBlockType', 'iu', addAttributeAtts);

/****************************************/

class LinkPanel extends wp.element.Component {
	render(){
		var el = wp.element.createElement;
		return el( wp.components.PanelBody,
			{
				title: 'Link',
				initialOpen: false
			},
			el( wp.components.TextControl,
				{
					label: 'URL',
					value: this.props.attributes.linkUrl,
					onChange: (newValue) => {
						this.props.setAttributes({ linkUrl: newValue });
					}
				}
			),
			el( wp.components.ToggleControl,
				{
					label: 'Open in new tab',
					checked: this.props.attributes.linkNewTab,
					onChange: (newValue) => {
						this.props.setAttributes({ linkNewTab: newValue });
					}
				}
			)
		);
	}
};
iu.blockUtils.link = {
	panel: LinkPanel
};

class BackgroundPanel extends wp.element.Component {
	render(){
		var el = wp.element.createElement;
		var self = this;

		return el( wp.components.PanelBody,
			{
				title: 'Background',
				initialOpen: false
			},
			el( wp.components.BaseControl,
				{
					label: 'Background color'
				},
				el( wp.compose.compose(
						wp.data.withSelect(function(select){
							return {
								colors: select('core/block-editor').getSettings().colors
							};
						})
					)(function(props){
						return el( wp.components.ColorPalette,
							{
								colors: props.colors,
								value: self.props.attributes.bgColor,
								onChange: (newValue) => {
									self.props.setAttributes({ bgColor: !newValue ? newValue : wp.blockEditor.getColorObjectByColorValue(props.colors, newValue).slug });
								},
								enableAlpha: true,
								asButton: true
							}
						);
					})
				)
			),
			el( wp.components.BaseControl,
				{
					label: 'Background gradient'
				},
				el( wp.compose.compose(
						wp.data.withSelect(function(select){
							return {
								gradients: select('core/block-editor').getSettings().gradients
							};
						})
					)(function(props){
						return el( wp.components.GradientPicker,
							{
								gradients: props.gradients,
								value: self.props.attributes.bgGradient,
								onChange: (newValue) => {
									self.props.setAttributes({ bgGradient: newValue });
								}
							}
						);
					})
				)
			),
			el( wp.components.BaseControl,
				{
					label: 'Background image'
				},
				this.props.attributes.bgImage === '' ? el( wp.blockEditor.MediaUploadCheck, 
					{},
					el( wp.blockEditor.MediaUpload,
						{
							title: 'Select background image',
							value: this.props.attributes.bgImage,
							onSelect: (image) => {
								this.props.setAttributes({ bgImage: image.url, bgImageThumb: image.mime == 'image/svg+xml' ? image.url : (image.sizes.thumbnail ? image.sizes.thumbnail.url : image.sizes.full.url) });
							},
							allowedTypes: ['image'],
							render: function(props){
								return el( wp.components.Button,
									{
										className: 'editor-post-featured-image__toggle',
										onClick: props.open,
									},
									'Select background image'
								);
							}
						}
					)
				)
				: el( wp.blockEditor.MediaUploadCheck,
					{},
					el( 'img',
						{
							src: this.props.attributes.bgImageThumb,
							style: {
								display: 'block'
							}
						}
					),
					el ( wp.components.Button,
						{
							isLink: true,
							isDestructive: true,
							onClick: () => {
								this.props.setAttributes({ bgImage: '', bgImageThumb: '' });
							}
						},
						'Remove backgorund image'
					)
				)
			),
			el( wp.components.TextControl,
				{
					label: 'Size',
					value: this.props.attributes.bgImageSize,
					onChange: (newValue) => {
						this.props.setAttributes({ bgImageSize: newValue });
					}
				}
			),
			el( wp.components.TextControl,
				{
					label: 'Position',
					value: this.props.attributes.bgImagePosition,
					onChange: (newValue) => {
						this.props.setAttributes({ bgImagePosition: newValue });
					}
				}
			)
		);
	}
};
iu.blockUtils.background = {
	panel: BackgroundPanel,
	styles: function(attributes){
		let style = {};
		if (attributes.bgImage){
			style.backgroundImage = 'url('+attributes.bgImage+')';
			style.backgroundSize = attributes.bgImageSize;
			style.backgroundPosition = attributes.bgImagePosition;
		} else if (attributes.bgGradient) {
			style.backgroundImage = attributes.bgGradient;
		}
		if (attributes.bgColor){
			style.backgroundColor = 'var(--wp--preset--color--'+attributes.bgColor+')';
		}

		return style;
	}
};

class SpacingPanel extends wp.element.Component {
	render(){
		const el = wp.element.createElement;
		return el( wp.components.PanelBody,
			{
				title: 'Spacing',
				initialOpen: false
			},
			el( wp.components.BaseControl,
				{
					label: 'Margin'	
				},
				el( 'div',
					{
						style: {
							display: 'flex',
							gap: '20px'
						}
					},
					el( wp.components.TextControl,
						{
							label: 'Top',
							value: this.props.attributes.marginTop,
							onChange: (newValue) => {
								this.props.setAttributes({ marginTop: newValue });
							}
						}
					),
					el( wp.components.TextControl,
						{
							label: 'Bottom',
							value: this.props.attributes.marginBottom,
							onChange: (newValue) => {
								this.props.setAttributes({ marginBottom: newValue });
							}
						}
					)
				)
			),
			el( wp.components.BaseControl,
				{
					label: 'Padding'	
				},
				el( 'div',
					{
						style: {
							display: 'flex',
							gap: '20px'
						}
					},
					el( wp.components.TextControl,
						{
							label: 'Top',
							value: this.props.attributes.paddingTop,
							onChange: (newValue) => {
								this.props.setAttributes({ paddingTop: newValue });
							}
						}
					),
					el( wp.components.TextControl,
						{
							label: 'Bottom',
							value: this.props.attributes.paddingBottom,
							onChange: (newValue) => {
								this.props.setAttributes({ paddingBottom: newValue });
							}
						}
					)
				),
				el( 'div',
					{
						style: {
							display: 'flex',
							gap: '20px'
						}
					},
					el( wp.components.TextControl,
						{
							label: 'Left',
							value: this.props.attributes.paddingLeft,
							onChange: (newValue) => {
								this.props.setAttributes({ paddingLeft: newValue });
							}
						}
					),
					el( wp.components.TextControl,
						{
							label: 'Right',
							value: this.props.attributes.paddingRight,
							onChange: (newValue) => {
								this.props.setAttributes({ paddingRight: newValue });
							}
						}
					)
				)
			)
		);
	}
};
iu.blockUtils.spacing = {
	panel: SpacingPanel,
	styles: function(attributes){
		let style = {};
		if (attributes.marginTop !== '') style.marginTop = attributes.marginTop;
		if (attributes.marginBottom !== '') style.marginBottom = attributes.marginBottom;
		if (attributes.paddingTop !== '') style.paddingTop = attributes.paddingTop;
		if (attributes.paddingBottom !== '') style.paddingBottom = attributes.paddingBottom;
		if (attributes.paddingLeft !== '') style.paddingLeft = attributes.paddingLeft;
		if (attributes.paddingRight !== '') style.paddingRight = attributes.paddingRight;

		return style;
	}
};

class GapPanel extends wp.element.Component {
	render(){
		const el = wp.element.createElement;
		return el( wp.components.PanelBody,
			{
				title: 'Gap',
				initialOpen: false
			},
			el( wp.components.TextControl,
				{
					label: 'Gap',
					value: this.props.attributes.gap,
					onChange: (newValue) => {
						this.props.setAttributes({ gap: newValue });
					}
				}
			)
		);
	}
};
iu.blockUtils.gap = {
	panel: GapPanel,
	styles: function(attributes){
		let style = {};
		if (attributes.gap !== '') style.gap = attributes.gap;

		return style;
	}
};

const RepeaterControl = function(attributes){
	const el = wp.element.createElement;

	const prepareLocalItems = function( dbItems ) {
        const rawItems = dbItems || [];
        return rawItems.map( function(item) {
            return Object.assign({}, item, { isClosed: true });
        } );
    };

    const [items, setItems] = wp.element.useState(prepareLocalItems(attributes.value));

    wp.element.useEffect( function() {
        const currentDbString = JSON.stringify( attributes.value || [] );
        const currentLocalCleanString = JSON.stringify( items.map( function(i) {
            const c = Object.assign({}, i);
            delete c.isClosed;
            return c;
        }));

        if ( currentDbString !== currentLocalCleanString ) {
            setItems( prepareLocalItems( attributes.value ) );
        }
    }, [ attributes.value ] );

    const itemsRef = wp.element.useRef( items );
    wp.element.useEffect( function() {
        itemsRef.current = items;
    }, [ items ] );

    const cleanItemsForDatabase = function( itemsArray ) {
        return itemsArray.map( function( item ) {
            const cleanItem = Object.assign( {}, item );
            delete cleanItem.isClosed;  
            return cleanItem;
        } );
    };

    const sortableCallbackRef = function( node ) {
        if ( !node ) return;         

        var $list = jQuery( node );
        if ( $list.data( 'ui-sortable' ) ) {
            return;
        }

        $list.sortable( {
            axis: 'y',
            handle: '.item-handle',
            cursor: 'grabbing',
            tolerance: 'pointer',
            start: function( event, ui ) {
                ui.item.data( 'startIndex', ui.item.index() );
                
                // FIX: Bezárjuk az éppen aktív fókuszt a beviteli mezőkről a húzás kezdetekor,
                // hogy az onBlur események ne zavarják meg a jQuery és a React állapotát.
                jQuery( ui.item ).find( 'input, textarea' ).trigger( 'blur' );
            },
            stop: function( event, ui ) {
                var oldIndex = ui.item.data( 'startIndex' );
                var newIndex = ui.item.index();

                if ( oldIndex === newIndex ) return;

                $list.sortable( 'cancel' );

                const reorderedItems = itemsRef.current.slice();
                
                const removedItems = reorderedItems.splice( oldIndex, 1 )[0]; 
                reorderedItems.splice( newIndex, 0, removedItems );

                setItems( reorderedItems );
                attributes.onChange(cleanItemsForDatabase( reorderedItems ));
            }
        } );
    };

    const updateLocalItem = function( index, key, value ) {
        const newLocalItems = items.slice();
        newLocalItems[index] = Object.assign( {}, newLocalItems[index] );
        newLocalItems[index][key] = value;
        setItems( newLocalItems );

        const field = attributes.fields.find(function(f){ return f.id === key && f.type === 'image' });
        if (field) attributes.onChange(cleanItemsForDatabase( newLocalItems ));
    };

    const toggleItemVisibility = function( index ) {
        var newLocalItems = items.slice();
        newLocalItems[index] = Object.assign( {}, newLocalItems[index] );
        newLocalItems[index].isClosed = ! newLocalItems[index].isClosed;
        setItems( newLocalItems );
    };

    const commitChanges = function(){
        attributes.onChange(cleanItemsForDatabase( items ));
    };

    const addItem = function() {
        const uniqueId = 'item_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        const newItem = { id: uniqueId, isClosed: false };
        const updated = items.concat( [ newItem ] );
        
        setItems( updated );
        attributes.onChange(cleanItemsForDatabase( updated ));
    };

    const removeItem = function( index ) {
        const updated = items.filter( function( _, i ) { return i !== index; } );
        setItems( updated );
        attributes.onChange(cleanItemsForDatabase( updated ));
    };

    const itemFields = items.map( function( item, index ) {
        if ( ! item.id ) {
            item.id = 'item_fallback_' + index + '_' + Date.now();
        }

        const visibilityIcon = item.isClosed ? 'arrow-down-alt2' : 'arrow-up-alt2';
        //var labelText = item.isClosed ? ( item.title || '#' + ( index + 1 )) : '';
        const labelText = '#'+(index +1);

        const controlButtonsRow = el( 'div', 
        	{
                style: { 
                    display: 'flex', 
                    justifyContent: 'space-between', 
                    alignItems: 'center'
                }
            },
            el( 'div', 
            	{ 
            		style: { 
            			display: 'flex', 
            			alignItems: 'center'
            		}
            	},
                el( 'span', 
                	{
                    	className: 'item-handle dashicons dashicons-menu-alt2',
                    	style: { 
                    		cursor: 'grab'
                    	}
                	}
                ),
                el( 'span',
                	{},
                	labelText
                )
            ),
            el( 'div', 
            	{
            		style: {
            			display: 'flex'
            		}
            	},
                el( wp.components.Button,
                	{
                        isSmall: true,
                        icon: visibilityIcon,
                        onClick: function(){ 
                        	toggleItemVisibility( index );
                        }
                	}
                ),
                el( wp.components.Button,
                	{
                        isDestructive: true,
                        isSmall: true,
                        icon: 'trash',
                        onClick: function(){ 
                        	removeItem( index );
                        }
                    }
                )
            )
        );

        var cardChildren = [ controlButtonsRow ];

        //if ( ! item.isClosed ) {
            cardChildren.push(
            	el( 'div',
            		{
            			style: {
            				display: item.isClosed ? 'none' : null
            			} 
            		},
	            	attributes.fields.map(function(field){
	            		switch(field.type){
	            			case 'image': {
	            				return el('div',
	            					{},
	            					!item[field.id] || !item[field.id].imageURL || item[field.id].imageURL == '' ? el( wp.blockEditor.MediaUploadCheck,
										{},
										el( wp.blockEditor.MediaUpload,
											{
												title: 'Select image',
												allowedTypes: ['image'],
												onSelect: function(image){
													updateLocalItem(index, field.id, {
														imageID: image.id,
														imageURL: image.url,
														imageThumbURL: image.mime == 'image/svg+xml' ? image.url : (image.sizes.thumbnail ? image.sizes.thumbnail.url : image.sizes.full.url)
													});
												},
												render: function(props){
													return el( wp.components.Button,
														{
															className: 'editor-post-featured-image__toggle',
															onClick: props.open,
														},
														'Select image'
													);
												}
											}
										)
									) : el( wp.blockEditor.MediaUploadCheck,
										{},
										el( 'img',
											{
												src: item[field.id].imageThumbURL,
												style: {
													display: 'block'
												}
											}
										),
										el ( wp.components.Button,
											{
												isLink: true,
												isDestructive: true,
												onClick: function(){
													updateLocalItem( index, field.id, null );
												},
												style: {
													marginBottom: '24px'
												}
											},
											'Remove image'
										)
									)
	            				)
	            			}
	            			case 'text': {
	            				return el( wp.components.TextControl,
	            					{
	            						placeholder: field.label || null,
	            						value: item[field.id] || '',
	            						onChange: function(newValue){
	            							updateLocalItem(index, field.id, newValue);
	            						},
	            						onBlur: commitChanges
	            					}
	            				);
	            			}
	            			case 'textarea': {
	            				return el( wp.components.TextareaControl,
	            					{
	            						placeholder: field.label || null,
	            						value: item[field.id] || '',
	            						onChange: function(newValue){
	            							updateLocalItem(index, field.id, newValue);
	            						},
	            						onBlur: commitChanges
	            					}
	            				);
	            			}
	            		}
	            	})
	            )
                /*el( TextControl, {
                    label: 'Slide Title',
                    value: item.title || '',
                    onChange: function( val ) { updateLocalItem( index, 'title', val ); },
                    onBlur: commitChangesToGutenberg
                } ),
                el( TextareaControl, {
                    label: 'Slide Description',
                    value: item.description || '',
                    rows: 4,
                    'data-id': item.id,
                    onChange: function( val ) { updateLocalItem( index, 'description', val ); },
                    onBlur: commitChangesToGutenberg,
                    onScroll: function( e ) {
                        item.scrollPos = e.target.scrollTop;
                    }
                } )*/
            );
        //}

        return el( 'div',
        	{
                key: item.id,
                className: 'sortable-item-row'
            },
            cardChildren
        );
    } );

	return el( 'div',
		{},
		el( 'div',
			{
				ref: sortableCallbackRef
			},
			itemFields
		),
		el( wp.components.Button,
			{
				className: 'editor-post-featured-image__toggle',
				onClick: addItem,
			},
			'Add item'
		)
	);
}
iu.blockUtils.repeater = {
	el: RepeaterControl
};