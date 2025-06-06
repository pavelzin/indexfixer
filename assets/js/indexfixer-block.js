(function() {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl, RangeControl, ToggleControl } = wp.components;
    const { useEffect, useState } = wp.element;
    const { __ } = wp.i18n;

    registerBlockType('indexfixer/not-indexed-posts', {
        title: __('IndexFixer - Niezaindeksowane posty', 'indexfixer'),
        description: __('Wyświetla listę niezaindeksowanych postów do linkowania wewnętrznego', 'indexfixer'),
        icon: 'search',
        category: 'widgets',
        keywords: [__('indexfixer'), __('seo'), __('google'), __('search console')],
        
        attributes: {
            title: {
                type: 'string',
                default: 'Niezaindeksowane posty'
            },
            count: {
                type: 'number',
                default: 5
            },
            autoCheck: {
                type: 'boolean',
                default: false
            }
        },

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { title, count, autoCheck } = attributes;
            const [posts, setPosts] = useState([]);
            const [loading, setLoading] = useState(true);

            // Pobierz podgląd postów
            useEffect(() => {
                setLoading(true);
                fetch(indexfixer_block.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'indexfixer_block_preview',
                        nonce: indexfixer_block.nonce,
                        count: count
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        setPosts(data.data);
                    } else {
                        setPosts([]);
                    }
                    setLoading(false);
                })
                .catch(() => {
                    setPosts([]);
                    setLoading(false);
                });
            }, [count]);

            return [
                wp.element.createElement(InspectorControls, {},
                    wp.element.createElement(PanelBody, {
                        title: __('Ustawienia', 'indexfixer'),
                        initialOpen: true
                    },
                        wp.element.createElement(TextControl, {
                            label: __('Tytuł', 'indexfixer'),
                            value: title,
                            onChange: (value) => setAttributes({ title: value })
                        }),
                        wp.element.createElement(RangeControl, {
                            label: __('Liczba postów', 'indexfixer'),
                            value: count,
                            onChange: (value) => setAttributes({ count: value }),
                            min: 1,
                            max: 20
                        }),
                        wp.element.createElement(ToggleControl, {
                            label: __('Automatyczne sprawdzanie co 24h', 'indexfixer'),
                            checked: autoCheck,
                            onChange: (value) => setAttributes({ autoCheck: value })
                        })
                    )
                ),
                wp.element.createElement('div', {
                    className: 'indexfixer-block-preview'
                },
                    wp.element.createElement('h3', {
                        style: { marginTop: 0, marginBottom: '15px', fontSize: '18px' }
                    }, title || 'Niezaindeksowane posty'),
                    
                    loading ? 
                        wp.element.createElement('p', {}, __('Ładowanie...', 'indexfixer')) :
                        posts.length === 0 ? 
                            wp.element.createElement('p', {}, __('Brak niezaindeksowanych postów 🎉', 'indexfixer')) :
                            wp.element.createElement('ul', {
                                style: { 
                                    listStyle: 'none', 
                                    padding: 0, 
                                    margin: 0 
                                }
                            }, posts.map((post, index) => 
                                wp.element.createElement('li', {
                                    key: index,
                                    style: {
                                        marginBottom: '12px',
                                        padding: '8px',
                                        background: '#f9f9f9',
                                        borderLeft: '3px solid #ff6b6b'
                                    }
                                },
                                    wp.element.createElement('div', {
                                        style: {
                                            fontWeight: 'bold',
                                            marginBottom: '4px'
                                        }
                                    }, post.title || 'Bez tytułu')
                                )
                            )),
                    
                    wp.element.createElement('p', {
                        style: {
                            background: '#fff3cd',
                            padding: '8px',
                            borderLeft: '3px solid #ffc107',
                            marginTop: '15px',
                            fontSize: '12px'
                        }
                    }, '💡 Ten widget pomaga w linkowaniu wewnętrznym. Gdy Google zaindeksuje post, automatycznie zniknie z listy.')
                )
            ];
        },

        save: function() {
            // Renderowanie odbywa się po stronie serwera
            return null;
        }
    });
})(); 