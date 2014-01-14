/**
 * User selector, makes use of the MagicSuggest jQuery plugin
 *
 * Options:
 * * value:       initial selected value. If not supplied, can be read from
 *                value of element
 * * multiple:    whether multiple selections can be made
 * * onePerLine:  whether selections show as blocks (one per line). Otherwise,
 *                they are displayed inline
 * * formElement: A separate form element to update with the selected value
 * * plugin:      options for magicsuggest
 */
(function($){
    "use strict";
    // require(magicsuggest.js, magicsuggest.css)
    $.fn.userSelector = function(options) {
        var element  = this;
        var defaults = {
            plugin: {
                data:'/ajax/user.php?groupID=0',
                displayField: 'name',
                hideTrigger: true,
                minChars: 2,
                allowFreeEntries: false,
                maxSelection: null,
                cls: 'no-trigger',
                selectionCls: '',
                selectionRenderer: function(a) {
                    // HTML-escape the user's name
                    return $('<span />').text(a.name).html();
                }
            },
            value: [],
            multiple: false,
            onePerLine: true,
            formElement: null
        }
        var settings = $.extend(true, {}, defaults, options)

        if (!settings.multiple) {
            settings.plugin.maxSelection = 1
        }
        if (settings.onePerLine) {
            settings.plugin.selectionCls += ' one-per-line'
        }

        // Load value from the element if it's there and not in settings
        if (!settings.value) {
            var value = element.val()
            if (value) {
                // Maybe it's a scalar, if so then reverse-lookup?
                settings.value = JSON.parse(value)
            }
        }
        if (!$.isArray(settings.value)) {
            settings.value = [settings.value]
        }

        // The plugin object
        var ms = $(element.magicSuggest(settings.plugin))
        element.data('magicsuggest', ms)

        // Add all values to selection
        ms.one('load.userSelector', function() {
            var suggest = this
            $.each(settings.value, function(k,v) {
                var id   = v
                var name = v
                if ($.isPlainObject(v)) {
                    id   = v.id
                    name = v.name
                }
                var selection = {id: id}
                selection[settings.plugin.displayField] = name
                suggest.addToSelection(selection)
            })
        })

        // Singles: overwrite input value
        // Multiples: Disable magicsuggest's JSON input and build an array of inputs
        ms.on('selectionchange.userSelector blur.userSelector', function() {
            var suggest   = this;
            var name      = suggest.getName()
            var value     = suggest.getValue()
            var baseInput = suggest.container.find('input[name="'+name+'"]')
            if (settings.multiple) {
                baseInput.prop('disabled', true)
                // Place new items within container, to ensure that they are
                // included after the base input (which gets added and removed)
                suggest.container.find('input[name="'+name+'[]"]').remove()
                $j.each(value, function(i,v){
                    suggest.container.append($j('<input type="hidden" />').attr('name', name+'[]').val(v))
                })
            }
            else {
                var singleValue = value.pop() || ''
                baseInput.val(singleValue)
                if (settings.formElement) {
                    settings.formElement.val(singleValue)
                }
            }
        })

        // Chain me
        return this;
    };
}(jQuery));

