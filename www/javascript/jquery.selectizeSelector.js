/**
 * Typeahead selector, makes use of the Selectize jQuery plugin
 * (https://brianreavis.github.io/selectize.js/)
 *
 * Options:
 * * value:       initial selected value.
 * * multiple:    whether multiple selections can be made
 * * onePerLine:  whether selections show as blocks (one per line). Otherwise,
 *                they are displayed inline
 * * formElement: A separate form element to update with the selected value
 *                (may be removed)
 * * ajax:        Parameters passed to jQuery.ajax() call in selectize.load()
 * * selectize:   options for selectize
 */
(function($){
    "use strict";
    $.fn.selectizeSelector = function(options) {

//        $j('#addUser').val('').selectize({
//            create: false,
//            persist: false,
//            searchField: 'name',
//            labelField: 'name',
//            valueField: 'id',
//            load: function(query, callback) {
//                if (!query.length) {
//                    callback();
//                }
//                $.ajax({
//                    url:      '/ajax/user.php',
//                    type:     'GET',
//                    data:     {groupID: 0, query: query},
//                    dataType: 'json'
//                })
//                .done(function(result) {
//                    callback(result)
//                })
//                .fail(function() {
//                    callback()
//                })
//            },
//            onInitialize: function() {
//                this.clear();
//                this.clearOptions();
//                this.refreshOptions(false)
//            }
//        })

        var element  = this;
        var settings = {}
        var defaults = {
            ajax: {
                url:      '/ajax/user.php',
                type:     'GET',
                data:     {groupID: 0},
                dataType: 'json'
            },
            selectize: {
                create: false,
                hideSelected: true,
                createOnBlur: true,
                persist: false,
                searchField: 'name',
                labelField: 'name',
                valueField: 'id',
//                create: function(input) {
//                    var freeEntry = {};
//                    freeEntry[this.settings.valueField]  = input;
//                    freeEntry[this.settings.searchField] = input;
//                    return freeEntry;
//                },
                load: function(query, callback) {
                    if (!query.length) {
                        callback();
                        return;
                    }
                    var ajaxSettings = settings.ajax;
                    ajaxSettings.data.query = query;
                    $.ajax(ajaxSettings)
                        .done(function(result) {
                            callback(result)
                        })
                        .fail(function() {
                            callback()
                        })
                },
                onInitialize: function() {
                    var self = this
                    self.clear();
                    self.clearOptions();
                    $j.each(settings.value, function(i,v) {
                        self.addOption(v)
                        self.addItem(v[self.settings.valueField])
                    });
                    self.refreshOptions(false);
                }
            },
            value: [],
            multiple: false,
            formElement: null
        }
        settings = $.extend(true, {}, defaults, options)

        // No multiple selections? set maxItems to 1
        if (!settings.multiple) {
            settings.selectize.maxItems = 1
        }
//        if (settings.onePerLine) {
//            settings.plugin.cls += ' one-per-line'
//        }

//        // Load value from the element if it's there and not in settings?
//        if (!settings.value) {
//            var value = element.val()
//            if (value) {
//                // Maybe it's a scalar, if so then reverse-lookup?
//                settings.value = JSON.parse(value)
//            }
//        }

        // If a single value is passed, set that as value?
        if (!$.isArray(settings.value)) {
            settings.value = [settings.value]
        }

        // The plugin object
        //
        element.val('').selectize(settings.selectize)
//        var ms = $(element.magicSuggest(settings.plugin))
//        element.data('selectize', ms)

//        // Add all values to selection
//        ms.one('load.userSelector', function() {
//            var suggest = this
//            $.each(settings.value, function(k,v) {
//                var id   = v
//                var name = v
//                if ($.isPlainObject(v)) {
//                    id   = v.id
//                    name = v.name
//                }
//                var selection = {id: id}
//                selection[settings.plugin.displayField] = name
//                suggest.addToSelection(selection)
//            })
//        })
//
//        // Singles: overwrite input value
//        // Multiples: Disable magicsuggest's JSON input and build an array of inputs
//        ms.on('selectionchange.userSelector blur.userSelector', function() {
//            var suggest   = this;
//            var name      = suggest.getName()
//            var value     = suggest.getValue()
//            var baseInput = suggest.container.find('input[name="'+name+'"]')
//            if (settings.multiple) {
//                baseInput.prop('disabled', true)
//                // Place new items within container, to ensure that they are
//                // included after the base input (which gets added and removed)
//                suggest.container.find('input[name="'+name+'[]"]').remove()
//                $j.each(value, function(i,v){
//                    suggest.container.append($j('<input type="hidden" />').attr('name', name+'[]').val(v))
//                })
//            }
//            else {
//                var singleValue = value.pop() || ''
//                baseInput.val(singleValue)
//                if (settings.formElement) {
//                    settings.formElement.val(singleValue)
//                }
//            }
//        })

        // Chain me
        return this;
    };
}(jQuery));

