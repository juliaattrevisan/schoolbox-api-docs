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
    $.selectizeSelector = {};

    /**
     * Helper method for retrieving a selectize's value from its select box.
     * The selected options are pre-selected.
     *
     * Also: if attribute `data-value-object` exists on the option, it's json
     * parsed and it's set as the return value.
     *
     * @param select
     * @param valueField
     * @param searchField
     * @returns {*[]}
     */
    $.selectizeSelector.getValueFromSelect = function(select, valueField, searchField) {
        valueField  = valueField || 'id';
        searchField = searchField || 'name';
        return $(select).find(':selected')
            .map(function() {
                var $this       = $(this),
                    valueObject = $this.data('valueObject'),
                    returnVal   = {};
                if (typeof valueObject === 'object') {
                    returnVal = valueObject;
                }
                returnVal[valueField]  = $this.val();
                returnVal[searchField] = $this.text();
                return returnVal;
            })
            .toArray();
    };

    /**
     * Helper method for retrieving a selectize's selected items from its
     * select box.
     *
     * @param select
     * @returns {*[]}
     */
    $.selectizeSelector.getItemsFromSelect = function(select) {
        return $(select).find(':selected')
            .map(function() {
                return this.value;
            })
            .toArray();
    };

    /**
     * Build a set of options which can be passed into `$.fn.selectizeSelector`.
     *
     * @param {Object} options Any overrides
     * @returns {Object}
     */
    $.selectizeSelector.buildUserSelectizeOptions = function (options) {
        let defaults = {
            ajax: {
                url: '/ajax/user.php',
                type: 'GET',
                dataType: 'json'
            },
            selectize: {
                searchField: ['name', 'first_name', 'last_name', 'full_name', 'external_id'],
            },
        };
        return $.extend(true, {}, defaults, options)
    };

    /**
     * Build a selectize especially for selecting users.
     *
     * @param {Object} options Any overrides
     * @returns {Object}
     */
    $.fn.userSelectizeSelector = function (options) {
        return this.selectizeSelector($.selectizeSelector.buildUserSelectizeOptions(options));
    };

    /**
     * Build a selectize.
     *
     * Defaults:
     * * value:     initial selected value.
     * * multiple:  whether multiple selections can be made
     * * ajax:      Parameters passed to jQuery.ajax() call in selectize.load()
     * * selectize: options for selectize
     *
     * @param {Object} options Any overrides
     * @returns {Object}
     */
    $.fn.selectizeSelector = function(options) {
        var element  = this;
        var settings = {}
        var defaults = {
            ajax: {
                type:     'GET',
                data:     {},
                dataType: 'json'
            },
            selectize: {
                dropdownParent: 'body',
                plugins: [],
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
                    // settings.value
                    var self = this;

                    // Don't clear options if the caller explicitly provided them.
                    if (!(
                        options.hasOwnProperty('selectize') &&
                        typeof(options.selectize) === 'object' &&
                        options.selectize !== null &&
                        options.selectize.hasOwnProperty('options'))
                    ) {
                        self.clear();
                        self.clearOptions();
                    }
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
                },
                onOptionRemove: function() {
                    // OptionRemove: clear loaded searches in case the user wants
                    // to re-add the one they just deleted (it is removed from
                    // the cached options when added).
                    this.loadedSearches = {}
                }
            },
            value: [],
            multiple: false
        }
        settings = $.extend(true, {}, defaults, options)

        // Only add the remove button plugin for multiple selections, because
        // the extra padding it adds screws up the cursor position.
        // No multiple selections? set maxItems to 1
        if (settings.multiple) {
            settings.selectize.plugins.push('remove_button')
        }
        else {
            settings.selectize.maxItems = 1
        }

        // If a single value is passed, set that as value?
        if (!$.isArray(settings.value)) {
            settings.value = [settings.value]
        }

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
        ];

        // Set up selectize on all elements
        element.each(function() {
            var singleElement = $(this);

            // Clear required attr to avoid jQuery validation conflict
            // (I'd like to add the validation rule to the form at this point,
            //  but there is no guarantee that the validation has even been set
            //  up yet: if it isn't, applying a rule will raise an error.)
            singleElement.removeAttr('required');

            singleElement.val('').selectize(settings.selectize);

            // Is this selectize inside a foundation modal ?
            if( singleElement.closest('[data-reveal]').length ){

                // Lock modal when dropdown opens
                singleElement.data('selectize').on('dropdown_open', function(){
                    Foundation.libs.reveal.locked = true;
                });

                // Unlock modal when dropdown closes
                singleElement.data('selectize').on('dropdown_close', function(){

                    // It appears the modal doesn't process the 'close' event until some period after the user action.
                    setTimeout(function(){
                        Foundation.libs.reveal.locked = false;
                    }, 200);   // empirical testing indicated '111' was minimum delay required.
                });
            }

            $.each(selectizeEvents, function(i,event) {
                singleElement.data('selectize').on(
                    event,
                    function() {
                        singleElement.trigger('selectize.'+event, arguments)
                    }
                )
            })
        });

        // Chain me
        return this;
    };
}(jQuery));

