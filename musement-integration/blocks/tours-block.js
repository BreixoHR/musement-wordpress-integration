(function (blocks, element, blockEditor, components, i18n) {
    const el = element.createElement;
    const useBlockProps = blockEditor.useBlockProps;
    const InspectorControls = blockEditor.InspectorControls;
    const { PanelBody, TextControl, SelectControl, RangeControl, MediaUpload } = components;
    const __ = i18n.__;

    blocks.registerBlockType('musement-integration/tours', {
        title: __('Musement Tours', 'musement-integration'),
        description: __('Muestra una lista de tours y disponibilidad de Musement.', 'musement-integration'),
        icon: 'location-alt',
        category: 'widgets',
        attributes: {
            city_id: {
                type: 'string',
                default: '',
            },
            limit: {
                type: 'number',
                default: 12,
            },
            title: {
                type: 'string',
                default: '',
            },
            image_url: {
                type: 'string',
                default: '',
            },
        },
        edit: function (props) {
            const attributes = props.attributes;
            const setAttributes = props.setAttributes;
            const blockProps = useBlockProps({
                className: 'musement-block-editor-preview'
            });

            // Parse cities list from localized data
            const cities = window.MusementBlockData && window.MusementBlockData.cities ? window.MusementBlockData.cities : [];
            const defaultCityId = window.MusementBlockData ? window.MusementBlockData.defaultCityId : '';
            const defaultCityName = window.MusementBlockData ? window.MusementBlockData.defaultCityName : '';

            // Map cities to options for SelectControl
            const cityOptions = [
                { label: __('Ciudad por defecto (de ajustes)', 'musement-integration'), value: '' }
            ];
            cities.forEach(function (city) {
                if (city.id && city.name) {
                    cityOptions.push({
                        label: city.name,
                        value: String(city.id)
                    });
                }
            });

            // Find current city name for preview
            let currentCityName = '';
            if (!attributes.city_id) {
                currentCityName = defaultCityName || __('No configurada', 'musement-integration');
            } else {
                const found = cities.find(function (c) {
                    return String(c.id) === String(attributes.city_id);
                });
                currentCityName = found ? found.name : __('ID: ' + attributes.city_id, 'musement-integration');
            }

            const currentTitle = attributes.title || (currentCityName ? __('Tours en ', 'musement-integration') + currentCityName : __('Tours disponibles', 'musement-integration'));

            return [
                // 1. Inspector Controls Sidebar
                        el(InspectorControls, { key: 'inspector' },
                            el(PanelBody, { title: __('Ajustes del Widget', 'musement-integration'), initialOpen: true },
                                el(SelectControl, {
                                    label: __('Selecciona la Ciudad', 'musement-integration'),
                                    value: attributes.city_id,
                                    options: cityOptions,
                                    onChange: function (val) {
                                        setAttributes({ city_id: val });
                                    }
                                }),
                                el(RangeControl, {
                                    label: __('Límite de Tours', 'musement-integration'),
                                    value: attributes.limit,
                                    min: 1,
                                    max: 12,
                                    onChange: function (val) {
                                        setAttributes({ limit: val });
                                    }
                                }),
                                el(TextControl, {
                                    label: __('Título del Widget (Opcional)', 'musement-integration'),
                                    value: attributes.title,
                                    placeholder: __('Ej: Tours en ', 'musement-integration') + currentCityName,
                                    onChange: function (val) {
                                        setAttributes({ title: val });
                                    }
                                }),
                                el(MediaUpload, {
                                    title: __('Imagen de la Actividad', 'musement-integration'),
                                    allowedTypes: ['image'],
                                    onSelect: function (media) {
                                        setAttributes({ image_url: media.url });
                                    },
                                    render: function (obj) {
                                        return el('div', {},
                                            el('button', {
                                                className: 'components-button is-button is-default',
                                                onClick: obj.open
                                            }, attributes.image_url ? __('Cambiar Imagen', 'musement-integration') : __('Seleccionar Imagen', 'musement-integration')),
                                            attributes.image_url && el('img', { src: attributes.image_url, style: { maxWidth: '100%', marginTop: '10px' } })
                                        );
                                    }
                                })
                            )
                        ),
                // 2. Editor Body Preview
                el('div', Object.assign({}, blockProps, { key: 'preview', style: {
                    border: '2px dashed #007cba',
                    borderRadius: '8px',
                    padding: '20px',
                    background: '#f0f6fa',
                    color: '#1e1e1e',
                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
                    boxShadow: '0 1px 3px rgba(0,0,0,0.05)'
                } }),
                    el('div', { style: { display: 'flex', alignItems: 'center', marginBottom: '15px' } },
                        el('span', { className: 'dashicons dashicons-location-alt', style: { fontSize: '24px', marginRight: '10px', color: '#007cba', width: '24px', height: '24px' } }),
                        el('h4', { style: { margin: 0, fontSize: '16px', fontWeight: '600', color: '#007cba' } }, __('Widget de Tours Musement', 'musement-integration'))
                    ),
                        // Image preview in editor
                        attributes.image_url && el('div', { style: { textAlign: 'center', marginBottom: '10px' } },
                            el('img', { src: attributes.image_url, style: { maxWidth: '100%' } })
                        ),
                )
            ];
        },
        save: function () {
            // Return null for dynamic rendering on PHP side
            return null;
        },
    });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n);
