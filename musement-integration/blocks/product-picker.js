(function (blocks, element, blockEditor, components, i18n, ServerSideRender) {
    var el = element.createElement;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;
    var ToggleControl = components.ToggleControl;
    var Placeholder = components.Placeholder;
    var __ = i18n.__;

    var products = (window.MUSEMENT_PRODUCTS && window.MUSEMENT_PRODUCTS.items) || [];
    var settingsUrl = (window.MUSEMENT_PRODUCTS && window.MUSEMENT_PRODUCTS.settingsUrl) || '';

    blocks.registerBlockType('musement-integration/product', {
        title: __('Musement: Producto individual', 'musement-integration'),
        description: __('Muestra el calendario y el boton de compra de una unica actividad de Musement, elegida de una lista.', 'musement-integration'),
        icon: 'tickets-alt',
        category: 'widgets',
        attributes: {
            uuid: { type: 'string', default: '' },
            title: { type: 'string', default: '' },
            showDetails: { type: 'boolean', default: true },
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            if (!products.length) {
                return el(
                    'div',
                    blockProps,
                    el(
                        Placeholder,
                        {
                            icon: 'tickets-alt',
                            label: __('Musement: Producto individual', 'musement-integration'),
                            instructions: __('Todavia no hay actividades sincronizadas. Ve a Musement > Ajustes, elige una ciudad y sincroniza productos.', 'musement-integration'),
                        },
                        el(
                            'a',
                            { href: settingsUrl, className: 'button button-primary', target: '_blank', rel: 'noreferrer' },
                            __('Ir a Musement > Ajustes', 'musement-integration')
                        )
                    )
                );
            }

            var options = [{ label: __('-- Selecciona una actividad --', 'musement-integration'), value: '' }].concat(
                products.map(function (product) {
                    return { label: product.title, value: product.uuid };
                })
            );

            return el(
                'div',
                blockProps,
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: __('Actividad', 'musement-integration'), initialOpen: true },
                        el(SelectControl, {
                            label: __('Producto Musement', 'musement-integration'),
                            value: attributes.uuid,
                            options: options,
                            onChange: function (value) {
                                var found = products.filter(function (product) {
                                    return product.uuid === value;
                                })[0];
                                setAttributes({ uuid: value, title: found ? found.title : '' });
                            },
                        }),
                        el(ToggleControl, {
                            label: __('Mostrar descripcion y detalles', 'musement-integration'),
                            checked: !!attributes.showDetails,
                            onChange: function (value) {
                                setAttributes({ showDetails: value });
                            },
                        })
                    )
                ),
                attributes.uuid
                    ? el(ServerSideRender, { block: 'musement-integration/product', attributes: attributes })
                    : el(Placeholder, {
                        icon: 'tickets-alt',
                        label: __('Musement: Producto individual', 'musement-integration'),
                        instructions: __('Selecciona una actividad en el panel lateral (icono de ajustes, arriba a la derecha del editor).', 'musement-integration'),
                    })
            );
        },
        save: function () {
            return null;
        },
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n,
    window.wp.serverSideRender
);
