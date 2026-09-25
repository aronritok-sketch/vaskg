( function( blocks, element, blockEditor, components ) {
    var el = element.createElement;
    var useEffect = element.useEffect;
    var useState = element.useState;
    var useRef = element.useRef;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var TextareaControl = components.TextareaControl;
    var Button = components.Button;

    blocks.registerBlockType( 'iu/html-slider', {
        edit: function( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            // Inicializáló szűrő: Az első betöltéskor minden meglévő elemet zárva indítunk
            var prepareLocalSlides = function( dbSlides ) {
                var rawSlides = dbSlides || [];
                return rawSlides.map( function( slide ) {
                    return Object.assign( {}, slide, { isClosed: true } );
                } );
            };

            // 1. HELYI STATE BUFFER
            var stateHook = useState( prepareLocalSlides( attributes.slides ) );
            var localSlides = stateHook[0];
            var setLocalSlides = stateHook[1];

            // Külső változások (Undo/Redo) kezelése – FONTOS: Csak akkor írjuk felül, ha a darabszám vagy a tartalom változik,
            // de nem bántjuk a helyi nyitva/zárva állapotokat, ha a Gutenberg frissül!
            useEffect( function() {
                var currentDbString = JSON.stringify( attributes.slides || [] );
                var currentLocalCleanString = JSON.stringify( localSlides.map( function(s) {
                    var c = Object.assign({}, s);
                    delete c.isClosed;
                    delete c.scrollPos;
                    return c;
                }));

                if ( currentDbString !== currentLocalCleanString ) {
                    setLocalSlides( prepareLocalSlides( attributes.slides ) );
                }
            }, [ attributes.slides ] );

            var localSlidesRef = useRef( localSlides );
            useEffect( function() {
                localSlidesRef.current = localSlides;
            }, [ localSlides ] );

            // Adatbázis tisztító szűrő a mentés előtt
            var cleanSlidesForDatabase = function( slidesArray ) {
                return slidesArray.map( function( slide ) {
                    var cleanSlide = Object.assign( {}, slide );
                    delete cleanSlide.isClosed;  
                    delete cleanSlide.scrollPos; 
                    return cleanSlide;
                } );
            };

            // JQUERY UI SORTABLE + SCROLL POZÍCIÓ HELYREÁLLÍTÁS
            var sortableCallbackRef = function( elementNode ) {
                if ( ! elementNode ) return;

                // Azonnali scroll visszaállítás a nyitott elemekre renderelés után
                var textareas = elementNode.querySelectorAll( '.sortable-slide-item-row textarea' );
                textareas.forEach( function( textarea ) {
                    var slideId = textarea.getAttribute( 'data-id' );
                    var match = localSlidesRef.current.find( function( s ) { return s.id === slideId; } );
                    if ( match && match.scrollPos !== undefined ) {
                        textarea.scrollTop = match.scrollPos;
                    }
                } );

                var $list = jQuery( elementNode );
                if ( $list.data( 'ui-sortable' ) ) {
                    return;
                }

                $list.sortable( {
                    axis: 'y',
                    handle: '.slide-drag-handle',
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

                        // Megszakítjuk a jQuery DOM manipulációt, hogy a React rendezzen
                        $list.sortable( 'cancel' );

                        // Új tömb másolása az élő referenciából (így az isClosed értékek is pontosan megmaradnak!)
                        var reorderedSlides = localSlidesRef.current.slice();
                        
                        // FIX: Biztonságos objektum kicsomagolás a index segítségével
                        var removedSlide = reorderedSlides.splice( oldIndex, 1 )[0]; 
                        reorderedSlides.splice( newIndex, 0, removedSlide );

                        // Egyszerre frissítjük a helyi bufferelt állapotot és a Gutenberget
                        setLocalSlides( reorderedSlides );
                        setAttributes( { slides: cleanSlidesForDatabase( reorderedSlides ) } );
                    }
                } );
            };

            // Gépelés kezelő (csak helyi state)
            var updateLocalItem = function( index, key, value ) {
                var newLocalSlides = localSlides.slice();
                newLocalSlides[index] = Object.assign( {}, newLocalSlides[index] );
                newLocalSlides[index][key] = value;
                setLocalSlides( newLocalSlides );
            };

            // Összecsukás / Kinyitás (csak helyi state, nem mentődik a DB-be)
            var toggleSlideVisibility = function( index ) {
                var newLocalSlides = localSlides.slice();
                newLocalSlides[index] = Object.assign( {}, newLocalSlides[index] );
                newLocalSlides[index].isClosed = ! newLocalSlides[index].isClosed;
                setLocalSlides( newLocalSlides );
            };

            var commitChangesToGutenberg = function() {
                setAttributes( { slides: cleanSlidesForDatabase( localSlides ) } );
            };

            // Új slide nyitva indul
            var addSlide = function() {
                var uniqueId = 'slide_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                var newSlide = { id: uniqueId, title: '', description: '', scrollPos: 0, isClosed: false };
                var updated = localSlides.concat( [ newSlide ] );
                
                setLocalSlides( updated );
                setAttributes( { slides: cleanSlidesForDatabase( updated ) } );
            };

            var removeSlide = function( index ) {
                var updated = localSlides.filter( function( _, i ) { return i !== index; } );
                setLocalSlides( updated );
                setAttributes( { slides: cleanSlidesForDatabase( updated ) } );
            };

            // MEZŐK GENERÁLÁSA (LOOP)
            var slideFields = localSlides.map( function( item, index ) {
                if ( ! item.id ) {
                    item.id = 'slide_fallback_' + index + '_' + Date.now();
                }

                var visibilityIcon = item.isClosed ? 'arrow-down-alt2' : 'arrow-up-alt2';
                var labelText = item.isClosed ? ( item.title || 'Slide #' + ( index + 1 ) + ' (Closed)' ) : '';

                var controlButtonsRow = el( 'div', {
                    style: { 
                        display: 'flex', 
                        justifyContent: 'space-between', 
                        alignItems: 'center', 
                        marginBottom: item.isClosed ? '0px' : '8px'
                    }
                }, [
                    // Fogantyú + Zárt állapotú felirat
                    el( 'div', { style: { display: 'flex', alignItems: 'center', gap: '10px' } }, [
                        el( 'span', {
                            className: 'slide-drag-handle dashicons dashicons-menu-alt2',
                            style: { cursor: 'grab', color: '#757575', fontSize: '20px' }
                        } ),
                        item.isClosed && el( 'span', { 
                            style: { fontWeight: '600', color: '#555', fontSize: '13px' } 
                        }, labelText )
                    ] ),

                    // Gombok (Összecsukás, Törlés)
                    el( 'div', { style: { display: 'flex', gap: '5px' } }, [
                        el( Button, {
                            isSmall: true,
                            icon: visibilityIcon,
                            onClick: function() { toggleSlideVisibility( index ); }
                        } ),
                        el( Button, {
                            isDestructive: true,
                            isSmall: true,
                            icon: 'trash',
                            onClick: function() { removeSlide( index ); }
                        } )
                    ] )
                ] );

                var cardChildren = [ controlButtonsRow ];

                // Feltételes renderelés: Ha az isClosed igaz, a mezők nincsenek a DOM-ban
                if ( ! item.isClosed ) {
                    cardChildren.push(
                        el( TextControl, {
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
                        } )
                    );
                }

                return el( 'div', {
                    key: item.id, // Szigorúan az egyedi ID-t használjuk kulcsként!
                    className: 'sortable-slide-item-row',
                    style: { 
                        background: '#f9f9f9', 
                        border: '1px solid #ccc', 
                        borderRadius: '4px', 
                        padding: '12px', 
                        marginBottom: '10px' 
                    }
                }, cardChildren );
            } );

            return el( 'div', useBlockProps(), [
                el( 'p', null, 'Open the Inspector Panel Settings sidebar to organize and drag slides rows.' ),
                
                el( InspectorControls, null,
                    el( PanelBody, { title: 'Slides Configuration', initialOpen: true }, [
                        el( 'div', {
                            ref: sortableCallbackRef, 
                            className: 'inspector-sortable-list-wrapper',
                            style: { padding: '5px 0' }
                        }, slideFields ),

                        el( Button, {
                            isPrimary: true,
                            onClick: addSlide,
                            style: { width: '100%', justifyContent: 'center', marginTop: '10px' }
                        }, 'Add New Slide' )
                    ] )
                )
            ] );
        },

        save: function() { return null; }
    } );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components );
