/**
 * Update the percentage-complete bars for each section
 */
(function ($) {
    $.fn.evaluation = function () {};

    /**
     * Prepare some common setup activity
     */
    $.fn.evaluation.initNavigators = function () {
        /* Submit all the questions before switching between sections
         */
        $("[data-evaluation-navigator]").click(function (event) {
            event.preventDefault();
            $(this).attr("disabled", "disabled").attr("readonly", "readonly");

            $.fn.evaluation.saveAll($(this).attr("href"));

            return false;
        });

        /* When someone finishes an assessment, loop through each question
         * and submit its values - then redirect. This avoids a weird timing
         * problem which sometimes happens, where it redirects before it
         * submits all the questionForm elements. This causes, in particular,
         * Matching Pairs to remain untouched resulting in NULL values.
         */
        $("[data-evaluation-finish]").click(function (event) {
            event.preventDefault();
            var $finishButton = $(this);
            $finishButton.attr("disabled", "disabled").attr("readonly", "readonly");
            var href = $finishButton.data("href");

            $finishButton.confirmDialog({
                confirmEvent: function () {
                    $.fn.evaluation.saveAll(href);
                },
                cancelEvent: function () {
                    $finishButton.removeAttr("disabled readonly");
                },
            });
        });
    };

    /**
     * Save each individual question
     */
    $.fn.evaluation.saveAll = function (href) {
        // Don't need to wait 2500ms now that AJAX calls are asynchronous
        var questions = $("[data-question]");
        var label = $('[data-relation="save-label"]');
        label.toastActivate({ text: "Saving results.." });

        saveForms(questions)
            .fail(function () {
                label.toastTimeout({ text: "Progress Save Failed!", css: "alert" });
                $("[data-evaluation-finish], [data-evaluation-navigator]")
                    .removeAttr("disabled")
                    .removeAttr("readonly");
            })
            .done(function () {
                var errors = false;
                questions.each(function (index, element) {
                    var pluginName = $(element).data("plugin");

                    /* Extract validation ruleset for this particular question type
                     */
                    var configuration = $.fn[pluginName].getValidationRules();

                    /* If configuration exists for this question type, apply settings
                     * otherwise ignore it
                     */
                    if (configuration != undefined) {
                        /* If the question is mandatory, apply validation requirements
                         */
                        if ($(element).find('span[data-mandatory="required"]').length) {
                            /* If a custom error location is desired
                             */
                            if (configuration.errorContainer != undefined) {
                                $(element).alaressValidate({
                                    onfocusout: false,
                                    errorPlacement: function (error, inputElement) {
                                        $(element).find(configuration.errorContainer).html(error);
                                    },
                                    ignore: ":hidden:not(:checkbox):not(:radio)",
                                });
                            } else {
                                $(element).alaressValidate({
                                    onfocusout: false,
                                    ignore: ":hidden:not(:checkbox):not(:radio)",
                                });
                            }

                            /* Apply custom rules if validation is required, in accordance with
                             * http://jquery.bassistance.de/validate/demo/
                             */
                            $(element).find(configuration.element).rules("add", configuration.rules);

                            /* If any aren't valid, mark as bad
                             */
                            if (!$(element).valid()) {
                                errors = true;
                            }
                        }
                    }
                });
                /* Remove loading gif; show success label
                 */
                label.toastTimeout({ text: "Progress Saved" });
                $("[data-evaluation-finish], [data-evaluation-navigator]")
                    .removeAttr("disabled")
                    .removeAttr("readonly");

                /* Detect errors, prevent redirection if any were made
                 * and hide that Saving box
                 */
                if (!errors) {
                    if (href.length > 0) {
                        location.href = href;
                    }
                } else {
                    // Scroll to first visible element with an error
                    // http://www.robertprice.co.uk/robblog/using-jquery-to-scroll-to-an-element/
                    var firstError = $(".error")
                        .filter(function () {
                            return $(this).css("display") === "block";
                        })
                        .first()
                        .closest("li");
                    $("html, body").animate({
                        // li is the corresponding parent item, to which the error pertains
                        scrollTop: $(firstError).offset().top,
                    });
                }
            });

        /**
         * Save each question form
         */
        function saveForms(questions) {
            // Run all AJAX calls asynchronously now, and wait for them all to finish before continuing.
            var ajaxes = [];
            questions.each(function (index, element) {
                var id = $(element).data("question");
                var pluginName = $(element).data("plugin");
                ajaxes.push(
                    $.retryPromise(function () {
                        return $.fn[pluginName].saveOne($("#questionForm-" + id));
                    }),
                );
            });
            return $.when.apply(null, ajaxes);
        }
    };

    /**
     * Update percentage complete bars
     *
     * @param options
     */
    $.fn.evaluationPercentageComplete = function (options) {
        var config = init(options);

        updateBar();

        /**
         * Init
         *
         * @param options
         * @returns {*}
         */
        function init(options) {
            var defaults = {};

            var derived = {
                result: [],
            };

            /* For every section's progress bar, distill important values
             * and stack them up
             */
            for (var i = 0; i < options.result.percentageComplete.length; i++) {
                var bar = "#evaluation-percentage-bar-" + options.result.percentageComplete[i].section_id;

                var barSelector = $(bar).find("[data-meter]");
                var valueSelector = $(bar).find("[data-perc-complete]");
                var percentage = options.result.percentageComplete[i].percentageComplete.toString();

                var element = {
                    barSelector: $(barSelector),
                    valueSelector: $(valueSelector),
                    value: percentage,
                };

                derived.result.push(element);
            }

            var merged = $.extend(true, defaults, derived);
            return merged;
        }

        /**
         * Update bar dimensions for every section
         */
        function updateBar() {
            for (var i = 0; i < config.result.length; i++) {
                config.result[i].barSelector.css("width", config.result[i].value + "%");
                config.result[i].valueSelector.text(config.result[i].value);
            }
        }
    };

    /**
     * Translate an integer to letters
     *
     * @param $num
     * @return string
     */
    $.fn.evaluation.findLetterIndex = function (num) {
        var firstLetter = Math.floor(num / 26);
        var lastLetter = num % 26;

        /* Special case for z, az, bz, etc.
         */
        if (!lastLetter) {
            firstLetter--;
            lastLetter = 26;
        }

        /* Convert from numeric to alpha
         */
        if (firstLetter) {
            var string = String.fromCharCode(firstLetter + 64) + String.fromCharCode(lastLetter + 64);
        } else {
            var string = String.fromCharCode(lastLetter + 64);
        }

        return string;
    };
})(jQuery);
