(function($) {
    var defaults = {
        value: [],
        ajax: {
            url:      '/curriculum/search',
            type:     'GET',
            data:     {},
            dataType: 'json'
        },
        multiple: true,
        selectize: {
            plugins: ['remove_button'],
            searchField: ['name', 'code'],
            render: {
                option: function (option, escape) {
                    // Add year level, source, etc
                    return '<div class="option"><strong>'
                        + escape(option.code) + '</strong>'
                        + (option.breadcrumb ? (' <em>(' + escape(option.breadcrumb) + ')</em>') : '')
                        + '<p>' + escape(option.name) + '</p></div>'
                        ;
                },
                item: function (item, escape) {
                    // Try to add a "view more" link
                    return '<div class="item" '
                        + 'title="'
                        + (item.breadcrumb ? ('(' + escape(item.breadcrumb) + ') ') : '')
                        + escape(item.name)
                        + '">'
                        + escape(item.code)
                        + '</div>'
                        ;
                }
            }
        }
    };
    $.fn.curriculumSelector = function(options) {
        this.selectizeSelector($.extend(true, {}, defaults, options));
        return this;
    };
})(jQuery);
