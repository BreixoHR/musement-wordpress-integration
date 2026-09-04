(function (blocks, element, blockEditor, i18n) {
    const el = element.createElement;
    const useBlockProps = blockEditor.useBlockProps;
    const __ = i18n.__;

    blocks.registerBlockType('musement-integration/tours', {
        title: __('Musement Tours', 'musement-integration'),
        icon: 'location-alt',
        category: 'widgets',
        edit: function () {
            return el(
                'div',
                useBlockProps(),
                __('Musement tours block', 'musement-integration')
            );
        },
        save: function () {
            return el(
                'div',
                { className: 'musement-tours' },
                __('Musement tours will render here.', 'musement-integration')
            );
        },
    });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n);
