(function() {

    /**
     * Marksheet
     */
    $.fn.marksheet = function(options) {

        /* Optional arguments
         */
        options = options || {}

        /* Store the config in this instance so private methods
         * can access it, only if the chosen element exists
         */
        var config = init(options, this);

        makeValidatable();
        cellValidation();
        selectGrouping();

        /**
         * Initialise various configured params and determine a bunch
         * of ids and stuff that we'll probably use elsewhere
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                element: element,

                /* Ajax submit marks whenever a dropdown is changed, a
                 * value is entered, or a comment is added
                 */
                submitOnChange: false,

                /* The url to submit data
                 */
                submitUrl: '/submission/returnWorkForm.php',

                /* wat? This didn't do anything other than change to false
                 * when something is cilcked
                 */
                ajax: true
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        function makeValidatable() {

            // the validator doesn't seem to be able to handle two inputs with the same name, so they need to be
            // uniqu-ified, then ajax submitted
            var inputs = config.element.find('input[data-mark-input]');

            inputs.each(function() {
                var self = $(this);
                var pInline = self.closest('form');
                var uid = pInline.find('input.uid');

                /* wat?
                 self.attr('name', self.attr('name').substring(0, self.attr('name').length - 2) + '[' + uid.val() + ']');
                 */
//				self.attr('name', self.attr('name') + '[' + uid.val() + ']');
            });
        }

        function cellValidation() {

            /* Only ajax submit on change if this setting is on
             */
            if (config.submitOnChange === true) {
                var allInlineForms = config.element.find('form');

                $('[data-mark-input], [data-comment]').change(function(){
                    var thisInlineForm = $(this).closest('form');

                    allInlineForms.not(thisInlineForm).removeClass('inline-submit');
                    thisInlineForm.addClass('inline-submit');
                    thisInlineForm.submit();
                });
            }

            /*
             * Depends on the presence of the jquery.validate library.
             * */
            jQuery.validator.addMethod("percentage", function(value, element) {
                if(jQuery.combobox.instances.length) {
                    var valid = jQuery.combobox.instances[0].selector.allSelectOptions;
                    if(jQuery.inArray(value, valid) != -1) {
                        return true;
                    }
                }
                return this.optional(element) || (parseFloat(value) <= 100);
            }, "Please enter a value out of 100");

            jQuery.validator.addMethod("markLimit", function(value, element) {
                if(jQuery.combobox.instances.length) {
                    var valid = jQuery.combobox.instances[0].selector.allSelectOptions;
                    if(jQuery.inArray(value, valid) != -1) {
                        return true;
                    }
                }
                var markLimit = parseInt($(element).closest('form').find('[data-validation-limit]').text());
                if (value !== "") {
                    var v = parseInt(value);
                    return v <= markLimit && v >= 0;
                } else {
                    return true;
                }
            }, "Amount must be less or equal to mark limit");


            jQuery.validator.addClassRules({
                percent: {
                    percentage: true
                },

                'out-of': {
                    markLimit: true
                }
            });

            config.element.each(function() {
                $(this).validate({
                    focusInvalid: false,
                    errorPlacement: function(error, element) {
                        // Attach to a table row if there is one there
                        var markingElement = element.closest('form');
                        if (markingElement.length) {
                            error.appendTo(markingElement);
                        } else {
                            error.insertAfter(element);
                        }
                    },
                    submitHandler: function(form) {
                        // Any inline submit forms? (there will be, if we are
                        // focused on a form element.) Use them if so.
                        // (Thankfully, the change event triggers before blur
                        //  in all browsers.)
                        var $form = $(form);

                        var label = $form.find('[data-relation="response-message"]');

                        label.labelActivate({
                            text: 'Preparing to save'
                        });

                        $form
                            .ajaxForm({url: config.submitUrl})
                            .on('done.ajaxForm', function() {
                                label.labelTimeout({
                                    text: 'Saved',
                                    css: 'success'
                                });
                                /* Redirect if a redirect input exists
                                 * (force redirect in Inline Grading)
                                 */
                                var redirect = $('input[name=redirect]');

                                if (redirect.length) {
                                    window.location.href = redirect.val();
                                }
                            })
                            .on('fail.ajaxForm', function() {
                                label.labelTimeout({
                                    text: 'Error',
                                    css: 'alert'
                                });
                            });
                        return false;
                    }
                });
            });

        }

        function selectGrouping() {
            $('#content select[data-mark-input]').each(function() {
                // if the select doesn't contain a separator for the error codes, slot it in
                var self = $(this);
                if (self.find('optgroup').length == 0) {
                    // find the first error (non-numeric) option
                    self.children('option').each(function() {
                        if(!isNumber($(this).val())) {
                            // insert before me
                            $(this).before('<optgroup></optgroup>');
                            // break the loop
                            return false;
                        }
                    });
                }
            });
        }

        /**
         * Determine if something is a number or not
         *
         * @param n
         * @returns {boolean|*|HTMLElement}
         */
        function isNumber(n) {
            return !isNaN(parseFloat(n)) && isFinite(n);
        }
    }

    /**
     * Controls for Averages
     */
    $.fn.marksheetAverages = function(options) {
        options = options || {}

        var config = init(options, $(this));

        hideTableRows();
        toggleTableRows();
        updateVisibility();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                element: element,

                /* When you click this element, rows are toggled
                 */
                averagesRowsSelector: '[data-toggle-averages]',

                /* These rows are shown when averagesRowsSelector is clicked
                 */
                averagesRows: '[data-sub-table-row]',

                /* Element containing the url for updating visibility of
                 * a Due Work/Quiz e.g. [data-visiblity-selector]
                 *
                 * data-visibility-selector, as an attribute, is also used
                 * to select a corresponding ajax feedback label
                 */
                visibilitySelector: 'data-visibility-selector',

                /* Icon replacement
                 * Icon to show when you can expand/collapse
                 */
                icon: {
                    expand: 'icon-add',
                    collapse: 'icon-collapse'
                }
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Hide all Averages rows
         */
        function hideTableRows() {
            $(config.averagesRows).hide();
        }

        /**
         * Toggle averages rows and change icon
         */
        function toggleTableRows() {
            $(config.averagesRowsSelector).click(function(event){
                event.preventDefault();

                if ($(config.averagesRows).is(':visible')) {
                    $(this).addClass(config.icon.expand).removeClass(config.icon.collapse);

                    $('[data-sub-table-row]').hide();
                }
                else {
                    $(this).removeClass(config.icon.expand).addClass(config.icon.collapse);

                    $('[data-sub-table-row]').show();
                }
            });
        }

        /**
         * Change visibility value for a particular Due Work/Quiz
         * Ajaxes the new value silently
         */
        function updateVisibility() {
            $('[' + config.visibilitySelector + ']').change(function(event) {
                event.preventDefault();

                var self = $(this);

                $.get($(this).val(), function(data) {
                    var indicator = self.attr(config.visibilitySelector);

                    var label = $('[data-identifier="'+indicator+'"]');
                    label.text('Saved').css({opacity:1}).removeClass('hide');
                    label.labelTimeout({duration: 3000});
                });
            });
        }
    }

    /**
     * Controls for Inputs
     */
    $.fn.marksheetInputs = function(options) {
        options = options || {}

        var config = init(options, $(this));

        everything();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                element: element,

                /* The url which receives the updated values via ajax
                 */
                url: '/ajax/submission/marks.php'
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Everything
         */
        function everything() {
            $(config.element).on('change', 'input', function (e) {
                var self = $(this);

                /* Disable the current input until ajax transaction completes
                 * This prevents update spamming
                 */
                self.prop('disabled', true);

                // set up the data variable for POSTing
                var data = {}
                data[this.name] = self.val();

                /* Label data identifier
                 */
                var identifier = self.attr('name') + '-weight';
                var label = $('[data-identifier="'+identifier+'"]');

                label.labelActivate({
                    text: 'Preparing to save'
                });

                /* Ajax new settings
                 */
                config.element
                    .ajaxForm({
                        type: 'POST',
                        url: config.url,
                        data: data
                    })
                    .on('done.ajaxForm', function(event, data) {
                        var tbody = $(config.element).children('tbody');

                        $.each(data, function (k, v) {
                            var userId = k,
                                gradient = v['avg-int'],
                                letter = v['avg-letter'];

                            var row = tbody.children('tr[data-id=' + userId + ']');

                            row.children('td').last()
                                .attr('class', 'gradient-' + gradient + '-bg').children('a')
                                .attr('class', 'gradient-' + gradient).text(letter);

                        });

                        /* Animate label feedback
                         */
                        label.labelTimeout({
                            text: 'Saved',
                            css: 'success'
                        });
                    })
                    .on('fail.ajaxForm', function() {
                        label.labelTimeout({
                            text: 'Value failed to save',
                            css: 'alert'
                        });
                    })
                    .on('always.ajaxForm', function() {
                        self.prop('disabled', false);
                    });
            });
        }
    }
}(jQuery));