(function () {
    /**
     * Short Answer Creator
     *
     * @param options
     */
    $.fn.shortCreate = function (options) {
        options = options || {};

        var config = init(options, $(this));

        /* Add custom validation rules
         */
        config.customValidationRules(config);

        /* Mash the validator on
         */
        applyValidator();

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

                /* Which validator to use
                 */
                mode: "create",

                /* Whether you came from the QuestionBank
                 */
                questionBank: false,

                /* An element containing all the available configuration options
                 */
                configuration: "#configuration",

                /* Setup of custom validation rules to use
                 * Call $.fn.addCustomRules_* here.
                 */
                customValidationRules: function () {},

                /* Actual validation rules
                 * Defaults to the full set. Can be extended/overridden via instance-specific options
                 * Note: If you wish to remove a default method, you must set it to false in your instance options
                 */
                rules: {
                    question: {
                        required: true,
                        minlength: 2,
                    },
                    marks: {
                        required: true,
                        minlength: 1,
                        greaterThanZero: true,
                    },
                    answer: {
                        required: true,
                        minlength: 2,
                        emptyAnswer: true,
                    },
                },

                /* Error messages
                 * Note: If you override a validation method's default message, you must define your custom messages
                 *       at the same granularity as the rules, otherwise instance-specific custom messages will not be displayed.
                 */
                messages: {
                    question: {
                        required: "Please enter a question.",
                        minlength: "Please enter a question.",
                    },
                    answer: {
                        required: "Please fill out an answer.",
                        minlength: "Please fill out an answer.",
                        emptyAnswer: "Please fill out an answer.",
                    },
                },

                errorPlacement: function (error, element) {
                    // position error label after generated textarea
                    if (element.is("textarea") && element.next().hasClass("mceEditor")) {
                        error.insertAfter(element.next());
                    } else {
                        error.insertAfter(element);
                    }
                },
            };

            var derived = {};

            var merged = $.extend(true, defaults, derived, options);

            return merged;
        }

        /* Validator
         */

        /**
         * Apply the validator to the form
         */
        function applyValidator() {
            $(config.element)
                .submit(function () {
                    // update underlying textareas before submit validation
                    $.each(window.sbProps.editors, function (prop, val) {
                        window.sbProps.editors[prop].updateSourceElement();
                    });
                })
                .alaressValidate({
                    onfocusout: false,
                    rules: config.rules,
                    messages: config.messages,

                    // Don't ignore radios and checkboxes, as these are actually hidden
                    ignore: "",

                    errorPlacement: function (error, element) {
                        /* If the error was found on the Marks field, just leave it
                         * to spawn naturally
                         *
                         * Position error label after generated textarea
                         */
                        if (element.is("textarea") && element.next().hasClass("mceEditor")) {
                            error.insertAfter(element.next());
                        } else {
                            error.insertAfter(element);
                        }
                    },
                });
        }
    };

    /**
     * Multiple Choice/Radio Creator
     */
    $.fn.multiRadioCreate = function (options) {
        options = options || {};

        var config = init(options, $(this));

        /* Default minOptions items to edit
         */
        addDefaultOptions();

        /* Update letter indexes
         */
        refreshList();

        /* Controls for each answer option
         */
        addNewAnswerOption();
        removeAnswerOption();

        /* Add custom validation rules
         */
        config.customValidationRules(config);

        /* Mash the validator on
         */
        applyValidator();

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

                /* Which validator to use
                 */
                mode: "create",

                /* The element which contains all the answer options to choooze from
                 */
                answerCollectionList: "#answerCollection",

                /* Minimum number of answer options to chooze from
                 */
                minOptions: 2,

                /* Maximum number of answer options to chooze from
                 */
                maxOptions: 702,

                /* Set to true to add a set of options on init
                 * Set to false for predefined starting options e.g. ratingAgree
                 */
                addDefaults: true,

                /* Quiz or Survey
                 */
                availability: "assess",

                /* Whether you came from the QuestionBank
                 */
                questionBank: false,

                /* An element containing all the available configuration options
                 */
                configuration: "#configuration",

                /* What POST var are answers stored in?
                 */
                answerName: "description",

                /* Custom method for generating an answer from this form.
                 * Accepts configuration object as a parameter,
                 * returns whatever answer should be passed to the form submit handler.
                 */
                generateAnswer: null,

                // Input name attribute for a returnUrl
                returnUrlName: "returnUrl",

                /* Setup of custom validation rules to use
                 * Call $.fn.addCustomRules_* here.
                 */
                customValidationRules: function (config) {
                    $.fn.addCustomRules_greaterThanZero(config);
                    $.fn.addCustomRules_somethingChecked(config);
                    $.fn.addCustomRules_emptyInputs(config);
                    $.fn.addCustomRules_uniqueOptions(config);
                },

                /* Actual validation rules
                 * Defaults to the full set. Survey-type will need to set somethingChecked: false as not rele
                 */
                rules: {
                    question: {
                        required: true,
                        minlength: 2,
                    },
                    marks: {
                        required: true,
                        minlength: 1,
                        greaterThanZero: true,
                    },
                    answer: false,
                    "answerDescription[]": {
                        emptyInputs: true,
                        hasUniqueOptions: "answerCollection",
                    },
                },

                /* Error messages
                 */
                messages: {
                    question: "Please enter a question.",
                },
            };

            var derived = {};

            var merged = $.extend(true, defaults, derived, options);

            return merged;
        }

        /**
         * Add the minimum number of options to choose from
         */
        function addDefaultOptions() {
            if (!$(config.answerCollectionList).find("[data-input]").length) {
                for (var i = 0; i < config.minOptions; i++) {
                    $(config.answerCollectionList).append($("[data-clone] [data-input]").clone());
                }
            }
        }

        /**
         * Reindex letter index
         */
        function refreshList() {
            var i = 1;

            /* Only reindex options that have been added
             * (ignore the clone)
             */
            $(config.answerCollectionList)
                .find("[data-input]")
                .each(function (index, element) {
                    $(element)
                        .find("[data-answer-code-representation]")
                        .html($.fn.evaluation.findLetterIndex(i) + ")");
                    $(element).find("[data-answer-code-value]").val(i);

                    /* Update checkbox id and label target, due to custom styles
                     */
                    var id = "answer-option-" + i;

                    $(element).find("[data-answer-code-representation]").parent("label").attr("for", id);
                    $(element).find("[data-answer-option]").attr("id", id).val(i);

                    i++;
                });
        }

        /**
         * Add a new answer option
         */
        function addNewAnswerOption() {
            $(document).on("click", "[data-add-answer]", function (event) {
                event.preventDefault();

                /* Obey limit
                 */
                var allInputs = $(config.answerCollectionList).find("[data-input]");

                if (allInputs.length < config.maxOptions) {
                    $(config.answerCollectionList).append($("[data-clone] [data-input]").clone());

                    refreshList();

                    /* Bring focus to the new clone
                     */
                    $(config.answerCollectionList).find("[data-answer-description]").last().focus();
                }

                return false;
            });
        }

        /**
         * Remove an answer option
         */
        function removeAnswerOption() {
            $(document).on("click", "[data-delete]", function (event) {
                event.preventDefault();

                /* Only remove when greater than three
                 */
                var allInputs = $(config.answerCollectionList).find("[data-input]");

                if (allInputs.length > config.minOptions) {
                    $(this).parents("[data-input]").remove();

                    refreshList();
                }
            });
        }

        /* Validator
         */

        /**
         * Apply the validator to the form
         */
        function applyValidator() {
            $(config.element)
                .submit(function () {
                    // update underlying textarea before submit validation
                    window.sbProps.editors["question"].updateSourceElement();
                })
                .alaressValidate({
                    onfocusout: false,
                    submitHandler: function () {
                        var theForm = $(config.element);

                        theForm.find('input[type="submit"]').prop("disabled", true); //prevent multiple submissions

                        /* Collate possible answers and their correctness status
                         */
                        var answerCollection = [];
                        if (typeof config.generateAnswer === "function") {
                            answerCollection = config.generateAnswer(config);
                        } else {
                            $.each($(config.answerCollectionList).find("[data-input]"), function (index, element) {
                                var elem = $(element);

                                var description = $.trim(elem.find("[data-answer-description]").val());
                                var code = $.trim(elem.find("[data-answer-code-value]").val());

                                /* Ignore empty answers that weren't removed
                                 */
                                if (description != "" && code != "") {
                                    var answer = {};
                                    answer[config.answerName] = description;
                                    answer.value = code;
                                    answer.correct = elem.find("[data-answer-option]").prop("checked");
                                    answerCollection.push(answer);
                                }
                            });
                        }

                        /* Collate the usual fields containing ids.
                         * Collate the checkboxes too but append all the possibilities as well!
                         *
                         * Common data
                         */
                        var data = {
                            question: window.sbProps.editors["question"].getData(),
                            sequence: theForm.find("input[name=sequence]").val(),
                            allAnswers: JSON.stringify(answerCollection),
                            configuration: JSON.stringify(
                                $(config.configuration).find("input, textarea, select").serializeArray(),
                            ),
                        };

                        /* Quiz-specific data
                         */
                        if (config.availability == "assess") {
                            data.marks = theForm.find("input[name=marks]").val();
                        }

                        /* Redirect url
                         */
                        var redirect = "";

                        /* Create in Quiz/Survey
                         */
                        if (config.mode == "create") {
                            data.type_id = theForm.find("select[name=type_id]").val();
                            data.assess_id = theForm.find("input[name=assess_id]").val();
                            data.section_id = theForm.find("input[name=section_id]").val();

                            redirect = "/" + config.availability + "/question/create/" + data.section_id + "/create";
                        } else {
                            /* Modify in Quiz/Survey
                             */
                            if (typeof config.questionBank == "undefined" || config.questionBank === false) {
                                data.assess_id = theForm.find("input[name=assess_id]").val();
                                data.type_id = theForm.find("input[name=type_id]").val();
                                data.section_id = theForm.find("input[name=section_id]").val();
                                data.question_id = theForm.find("input[name=question_id]").val();

                                redirect = "/" + config.availability + "/summary/" + data.assess_id;
                            } else {
                                /* Modify directly in the Question Bank
                                 */
                                data.type_id = theForm.find("input[name=type_id]").val();
                                data.question_bank_id = theForm.find("input[name=question_bank_id]").val();
                                data.assess_id = theForm.find("input[name=assess_id]").val();
                                data.section_id = theForm.find("input[name=section_id]").val();
                                var catId = theForm.find("input[name=question_category_id]").val();
                                if (typeof catId === "undefined") {
                                    catId = "0";
                                }
                                redirect =
                                    config.questionBank.url +
                                    "/" +
                                    catId +
                                    "/" +
                                    data.assess_id +
                                    "/" +
                                    data.section_id;
                            }
                        }

                        // Whack the return URL on if we have one

                        var returnUrl = theForm.find("input[name=" + config.returnUrlName + "]").val();

                        if (returnUrl === "" || returnUrl === undefined) {
                            returnUrl = "";
                        } else {
                            returnUrl = "?" + config.returnUrlName + "=" + returnUrl;
                        }

                        /* Submit via POST and redirect to question creation screen upon done
                         */
                        $.post(theForm.attr("action"), data, function (responseText) {
                            window.location = redirect + returnUrl;
                        });

                        return false;
                    },

                    rules: config.rules,
                    messages: config.messages,

                    errorPlacement: function (error, element) {
                        /* If the error has to do with the entire list,
                         * spawn the error at the bottom
                         * http://stackoverflow.com/questions/1318076/jquery-hasattr-checking-to-see-if-there-is-an-attribute-on-an-element
                         */
                        var hasCorrectAnswer = $(element).attr("data-correct-answer");
                        var hasAllDescriptors = $(element).attr("data-answer-description");
                        var isAnswerOption = $(element).attr("data-answer-option");

                        if (typeof hasCorrectAnswer !== typeof undefined && hasCorrectAnswer !== false) {
                            error.insertAfter(element.closest(config.answerCollectionList));
                        } else if (typeof hasAllDescriptors !== typeof undefined && hasAllDescriptors !== false) {
                            error.insertAfter(element.closest(config.answerCollectionList));
                        } else if (typeof isAnswerOption !== typeof undefined && isAnswerOption !== false) {
                            error.insertAfter(element.closest(config.answerCollectionList));
                        } else if (element.is("textarea") && element.next().hasClass("mceEditor")) {
                            /* If the error was found on the Marks field, just leave it
                             * to spawn naturally
                             *
                             * If the error was found on one of the answer options,
                             * spawn it naturally
                             */
                            error.insertAfter(element.next());
                        } else {
                            error.insertAfter(element);
                        }
                    },

                    // Don't ignore radios and checkboxes, as these are actually hidden
                    ignore: "",
                });
        }
    };

    /**
     * Fillgap Creator
     *
     * @param options
     */
    $.fn.fillgapCreate = function (options) {
        options = options || {};

        var config = init(options, $(this));

        start();

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
                targetElements: ["#answerBox"],
                fileBrowserCategoryId: 0,
            };

            var derived = {
                /* CSS for indicating which data to "make blank"
                 */
                css: "span.selected-word { background: #cae9b9; }",
            };

            var merged = $.extend(true, defaults, derived, options);

            return merged;
        }

        /**
         * Set everything up
         */
        function start() {
            document.dispatchEvent(
                new CustomEvent("EDITOR__CREATE", {
                    detail: {
                        targetElements: config.targetElements,
                        toolbar: "simple",
                        addToToolbar: ["|", "blank"],
                        fileBrowserCategoryId: config.fileBrowserCategoryId,
                    },
                }),
            );

            $("#createQuestionForm").shortCreate({
                rules: {
                    answer: {
                        hasClozeField: "selected-word",
                    },
                },
                customValidationRules: function (config) {
                    $.fn.addCustomRules_greaterThanZero(config);
                    $.fn.addCustomRules_emptyAnswer(config);
                    $.fn.addCustomRules_containsClozeField(config);
                },
            });
        }
    };
})(jQuery);
