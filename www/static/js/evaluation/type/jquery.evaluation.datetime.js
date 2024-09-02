(function ($) {
    /**
     * Constructor
     *
     * @param options
     */
    $.fn.evaluationDateTime = function (options) {
        var config = init(options);

        initChange();

        /**
         * Set up options
         *
         * @param options
         * @returns {*}
         */
        function init(options) {
            var defaults = {
                id: 0,
            };

            var derived = {
                questionForm: $("#questionForm-" + options.id),
            };

            return $.extend(true, defaults, derived, options);
        }

        /**
         * Save response upon change
         */
        function initChange() {
            // Wait for the datetime input conversion to apply, then save
            // Also: don't submit on pressing enter
            config.questionForm
                .find("[data-datepicker]")
                .on("datepickerChange", function () {
                    $.fn.evaluationDateTime.saveOne(config.questionForm);
                })
                .on("keydown", function (event) {
                    if (event.keyCode === 13) {
                        event.preventDefault();
                    }
                });
        }
    };

    /**
     * Save individual form contents
     *
     * @param element
     * @returns jqXHR
     */
    $.fn.evaluationDateTime.saveOne = function (element) {
        return $.ajax({
            type: "post",
            url: $(element).attr("action"),
            data: $(element).serialize(),
            /* Callback on success
             */
            success: function (responseText) {
                var result = new Function("return " + responseText);

                $.fn.evaluationPercentageComplete({
                    result: result(),
                });
            },
        });
    };

    /**
     * Decide what the validation rules are for this particular
     * question type. Optionally specify errorContainer if you want
     * the validation messages to appear in a special location,
     * otherwise leave it undefined for default behaviour
     *
     * @returns {{element: string, rules: {required: boolean, minlength: number}, errorContainer: string}}
     */
    $.fn.evaluationDateTime.getValidationRules = function () {
        return {
            element: "input[name=answer]",
            rules: {
                required: true,
                minlength: 1,
            },
            errorContainer: '[data-evaluationerrors="1"]',
        };
    };
})(jQuery);
