(function($) {
    var defaults = {
        value: [],
        ajax: {
            url:      '/api/curriculum/',
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

    /**
     * Converts values into the format required by selectize
     *
     * Selectize requires multiple items to be passed in an array, not an object
     *
     * @param {mixed} value
     * @return Array
     */
    function normaliseValues(value)
    {
        // Arrays are fine
        if(value instanceof Array){
            return value;
        }

        // Differentiate between an object representing a single node, and an object containing nodes
        if(value instanceof Object){

            // Does it have an ID ?
            if(value.hasOwnProperty('id')){
                return [value];
            }

            // No ID, conclude we have an object containing node objects
            var arrayValue = [];
            for(var i in value){
                if (value.hasOwnProperty(i)) {
                    arrayValue[i] = value[i];
                }
            }
            return arrayValue;
        }

        // Not sure what we have here, just wrap in array
        return [value];
    }


    $.fn.curriculumSelector = function(options) {
        if (!options) {
            options = {};
        }
        options.value = normaliseValues('value' in options ? options.value : []);
        this.selectizeSelector($.extend(true, {}, defaults, options));
        return this;
    };
})(jQuery);
