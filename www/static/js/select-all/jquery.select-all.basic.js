(function () {
    /**
     * A lightweight select-all plugin that changes the checkboxes (defined by
     * "selector" string), whenever the select-all checkbox (defined by the element
     * upon which this plugin instantiates) is ticked
     *
     * Note that this basic algorithm, without any callbacks, is intended to service
     * multiple thousands of checkboxes
     *
     * @param options
     */
    $.fn.selectAllBasic = function (options) {
        options = options || {};

        let config = init(options, $(this));

        start();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            let defaults = {
                /* The select-all checkbox
                 */
                element: element,

                /* The selector string for all the checkboxes that will be affected
                 * by the select-all checkbox; it's the selector string so we can
                 * append ':checked' and avoid using filter()
                 */
                selector: "[data-select-all-group]",

                /* Special css styles to apply in particular scenarios
                 */
                css: {
                    /* Occurs when some, but not all, checkboxes in a group are ticked
                     */
                    indeterminate: "indeterminate",
                },

                /* Decide the default state of the checkbox
                 * By default, it'll be unticked (false) by default
                 */
                defaultState: false,

                /* Callbacks
                 * Use these callbacks to augment the default behaviour with whatever
                 * specialty actions you need. Add more callbacks as required instead
                 * of adding more logic to this plugin, which should be able to service
                 * thousands of checkbox elements
                 */

                /* This callback occurs when the select-all checkbox is ticked
                 */
                onAfterSelectAllTick: function (allCheckboxes) {},

                /* This callback occurs when the select-all checkbox is unticked
                 */
                onAfterSelectAllUntick: function (allCheckboxes) {},

                /* This callback occurs after the select-all checkbox is changed
                 * and occurs regardless of whatever state the select-all checkbox
                 * has become
                 */
                onAfterSelectAllChange: function (allCheckboxes) {},

                /* This callback occurs after a checkbox is ticked
                 */
                onAfterCheckboxTick: function (checkboxTicked) {},

                /* This callback occurs after a checkbox is unticked
                 */
                onAfterCheckboxUntick: function (checkboxUnticked) {},

                /* This callback occurs after a checkbox state is changed
                 */
                onAfterCheckboxChange: function (checkbox) {},
            };

            return $.extend(defaults, options);
        }

        /**
         * Set up listeners and callbacks
         *
         * Avoid woefully inefficient implementations of select-all
         * You can shrink this further but I'm going to humour the select-all-for-group var
         */
        function start() {
            const selectAll = config.element;
            const selector = config.selector;
            const allCheckboxes = $(selector);
            const selectedCheckboxes = selector + ":checked";

            /* Assign default state
             */
            selectAll.prop("checked", config.defaultState);

            selectAll.on("click", function (event) {
                if ($(this).is(":checked")) {
                    allCheckboxes.prop("checked", true);

                    config.onAfterSelectAllTick(allCheckboxes);
                } else {
                    allCheckboxes.prop("checked", false);

                    config.onAfterSelectAllUntick(allCheckboxes);
                }

                allCheckboxes.trigger('change');

                config.onAfterSelectAllChange(allCheckboxes);

                /* Remove indeterminate status if that was previously set
                 */
                $(selectAll).removeClass(config.css.indeterminate);
            });

            /* Checkboxes in the group affect the select-all parent
             */
            allCheckboxes.on("change", function (event) {
                if ($(this).prop("checked") === false) {
                    selectAll.prop("checked", false);

                    config.onAfterCheckboxTick($(this));
                } else if ($(selectedCheckboxes).length === allCheckboxes.length) {
                    selectAll.prop("checked", true);

                    config.onAfterCheckboxUntick($(this));
                }

                config.onAfterCheckboxChange(allCheckboxes);

                /* Check indeterminate select-all state
                 * Occurs when some, but not all, checkboxes in a group are ticked
                 */
                let numSelected = $(selectedCheckboxes).length;

                if (numSelected && numSelected < allCheckboxes.length) {
                    $(selectAll).addClass(config.css.indeterminate);
                } else {
                    $(selectAll).removeClass(config.css.indeterminate);
                }
            });
        }
    };
})(jQuery);
