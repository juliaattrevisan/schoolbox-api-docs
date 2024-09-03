/**
 * Reusable Evaluation validation rules
 */
(function () {
    /**
     * Greater Than Zero
     */
    $.fn.addCustomRules_greaterThanZero = function (config) {
        $.validator.addMethod(
            "greaterThanZero",
            function (value, element) {
                return this.optional(element) || parseFloat(value) > 0;
            },
            "Amount must be greater than zero",
        );
    };

    /**
     * Empty Inputs
     *
     * You must provide a description for each answer option
     */
    $.fn.addCustomRules_emptyInputs = function (config) {
        $.validator.addMethod(
            "emptyInputs",
            function (value, element) {
                var fieldsAreFilled = true;

                $.each($(config.answerCollectionList).find("[data-input]"), function (index, element) {
                    var description = $.trim($(element).find("[data-answer-description]").val());

                    /* Ignore empty answers that weren't removed
                     */
                    if (description == "") {
                        fieldsAreFilled = false;
                    }
                });

                return fieldsAreFilled;
            },
            "Please fill out all answers",
        );
    };

    /**
     * Something Checked
     *
     * At least one of the answer options must be marked as "correct"
     */
    $.fn.addCustomRules_somethingChecked = function (config) {
        $.validator.addMethod(
            "somethingChecked",
            function (value, element) {
                return $(config.answerCollectionList).find("[data-input] [data-answer-option]:checked").length > 0;
            },
            "Please select at least one correct answer",
        );
    };

    /**
     * Answer Field
     *
     * @param config
     */
    $.fn.addCustomRules_emptyAnswer = function (config) {
        $.validator.addMethod(
            "emptyAnswer",
            function (value, element) {
                var fieldsAreFilled = true;

                var description = $.trim($("[data-answer-description]").val());

                /* Ignore empty answers that weren't removed
                 */
                if (description == "") {
                    fieldsAreFilled = false;
                }

                return fieldsAreFilled;
            },
            "Please fill out answer",
        );
    };

    /**
     * Number
     *
     * @param config
     */
    $.fn.addCustomRules_isNumber = function (config) {
        $.validator.addMethod(
            "isNumber",
            function (value, element) {
                return !isNaN(value);
            },
            "Amount must be a number",
        );
    };

    /**
     * Ensures a Cloze Question's Answer contains at least one gap to be filled
     *
     * @param config
     */
    $.fn.addCustomRules_containsClozeField = function (config) {
        $.validator.addMethod(
            "hasClozeField",
            function (value, element, param) {
                var text = $(element).val();
                return $(text).find("." + param).length > 0;
            },
            "Answer must contain at least 1 Cloze field",
        );
    };

    /**
     * Ensures a Multi-Choice question's answers are each unique
     *
     * Uniqueness is case-sensitive, to cater for questions that depend on exact Capitalisation
     *
     * Rule can either set param = bool|string. If string, interpreted as parent to filter selection.
     *
     * @param config
     */
    $.fn.addCustomRules_uniqueOptions = function (config) {
        $.validator.addMethod(
            "hasUniqueOptions",
            function (value, element, param) {
                var nameAtt = $(element).attr("name");
                var parentSelector = "string" === typeof param ? "#" + param : "";
                var $answerOptions = $(parentSelector + " [name='" + nameAtt + "']");
                var obj = {};
                $.each($answerOptions, function (idx, elem) {
                    var val = $(elem).val();

                    // Don't attempt to create empty object keys
                    if ("" === val.trim()) {
                        return false;
                    }
                    obj[val] = true;
                });
                return $answerOptions.length === Object.keys(obj).length;
            },
            "Each answer must be unique",
        );
    };
})(jQuery);
