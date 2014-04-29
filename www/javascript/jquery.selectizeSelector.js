/**
 * Typeahead selector, makes use of the Selectize jQuery plugin
 * (https://brianreavis.github.io/selectize.js/)
 *
 * Options:
 * * value:     initial selected value.
 * * multiple:  whether multiple selections can be made
 * * ajax:      Parameters passed to jQuery.ajax() call in selectize.load()
 * * selectize: options for selectize
 */
(function($){
    "use strict";
    $.fn.selectizeSelector = function(options) {
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
                plugins: ['remove_button'],
                create: false,
                hideSelected: true,
                createOnBlur: true,
                persist: false,
                searchField: 'name',
                labelField: 'name',
                valueField: 'id',
                load: function(query, callback) {
                    // Load: get values from AJAX, and callback with result
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
                    // Initialize: clear existing options, and add items from
                    // setttings.value
                    var self = this
                    self.clear();
                    self.clearOptions();
                    $.each(settings.value, function(i,v) {
                        // Is settings.value a list of text values, instead of a
                        // list of objects? (Happens on the email form page,
                        // where a list of email addresses is passed.)
                        // Create an object out of the text value.
                        if (!$.isPlainObject(v)) {
                            var vValue = v
                            v = {}
                            v[self.settings.labelField] = vValue
                            v[self.settings.valueField] = vValue
                        }
                        self.addOption(v)
                        self.addItem(v[self.settings.valueField])
                    });
                    self.refreshOptions(false);
                }
            },
            value: [],
            multiple: false
        }
        settings = $.extend(true, {}, defaults, options)

        // No multiple selections? set maxItems to 1
        if (!settings.multiple) {
            settings.selectize.maxItems = 1
        }

        // If a single value is passed, set that as value?
        if (!$.isArray(settings.value)) {
            settings.value = [settings.value]
        }

        // The plugin object
        element.val('').selectize(settings.selectize)

        // For convenience: bind all selectize's events to the element, in the
        // selectize namespace.
        var selectizeEvents = [
            'change',
            'item_add',
            'item_remove',
            'clear',
            'option_add',
            'option_remove',
            'option_clear',
            'dropdown_open',
            'dropdown_close',
            'type',
//            'load',
//            'destroy'
        ]
        $.each(selectizeEvents, function(i,event) {
            element.data('selectize').on(
                event,
                function() {
                    element.trigger('selectize.'+event, arguments)
                }
            )
        })

        // Chain me
        return this;
    };
}(jQuery));

