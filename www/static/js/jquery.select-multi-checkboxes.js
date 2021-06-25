/**
 * Enables the selection of multiple checkboxes at once.
 *
 * Useful for long lists of checkboxes.
 *
 * Use Case:
 * 1. System presents a list of checkboxes
 * 2. User checks a checkbox in the list
 * 3. User holds the 'shift' key and selects another checkbox in the list
 * 4. System set checked state of all intervening checkboxes to match the newly selected checkbox's state
 */
(function ($) {
    var pluginName = 'selectMulti';

    var defaults = {
        labelSelector:    'label',                    // How to find all checkboxes labels in some parent element
        checkboxSelector: 'input[type=checkbox]'      // How to find all checkboxes in some parent element
    };

    /**
     * Constructor
     *
     * @param {dom} element
     * @param {Object} options
     */
    function SelectMulti(element, options) {

        var config  = $.extend(true, {}, defaults, options || {});  // Plugin config
        var mostRecentChangedIndex;                                 // Track the most recently changed checkbox

        // Get handles to relevant DOM elements
        var $listParent     = $(element),                           // Container that contains checkbox list
            $checkboxLabels = $listParent.find('label'),
            $checkboxes     = $listParent.find('input[type=checkbox]');

        attachListeners(element);

        /**
         * Attach callbacks to DOM element actions
         *
         * @param element
         */
        function attachListeners(element) {
            $(element).on('click', config.labelSelector, function (e) {
                  handleCheckboxLabelClick(e, e.target);
            });
        }

        /**
         * Handle the user clicking on a checkbox/label
         *
         * @param event The event that occurred
         * @param element The element the event occurred on
         */
        function handleCheckboxLabelClick(event, element) {
            var labelIndex      = $checkboxLabels.index(element);

            // Was this a shiftClick on a different checkbox ?
            // And is this not the first checkbox changed for this component ?
            if (event.shiftKey && typeof mostRecentChangedIndex === 'number' && mostRecentChangedIndex !== labelIndex) {

                // Change all intervening checkboxes to the new value
                var changedCheckbox = $checkboxes.get(labelIndex),
                    newState        = !$(changedCheckbox).prop('checked'),
                    increment       = labelIndex > mostRecentChangedIndex ? +1 : -1;
                for (var i = mostRecentChangedIndex; i !== labelIndex; i += increment) {
                    var $checkbox = $($checkboxes.get(i));

                    $checkbox.prop('checked', newState);

                    // Ensure 'change' listeners get triggered
                    $checkbox.trigger('change');
                }
                clearSelection();
            }

            // Always record the most-recently toggled checkbox
            mostRecentChangedIndex = labelIndex;
        }

        /**
         * Clears any text selection that occurred when shift+clicking a selection of text-boxes
         *
         * Note you can suppress this behaviour using the 'noselect' CSS class
         */
        function clearSelection() {
            if (document.selection) {
                document.selection.empty();
            } else if (window.getSelection) {
                window.getSelection().removeAllRanges();
            }
        }
    }

    /**
     * Register plugin with jquery
     *
     * @param options
     * @returns {*}
     */
    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' + pluginName, new SelectMulti(this, options));
            }
        });
    };
}(jQuery));
