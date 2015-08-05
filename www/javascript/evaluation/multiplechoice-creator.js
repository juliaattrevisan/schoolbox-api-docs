(function() {

    /**
     * Multiple Choice/Radio
     * Create
     */
    $.fn.multiRadioCreate = function(options) {
        options = options || {};

        var config = init(options, $(this));

        /* Default minOptions items to edit
         */
        addDefaultOptions();

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
                mode: 'create',

                /* The element which contains all the answer options to choooze from
                 */
                answerCollectionList: '#answerCollection',

                /* Minimum number of answer options to chooze from
                 */
                minOptions: 2,

                /* Maximum number of answer options to chooze from
                 */
                maxOptions: 702,

                /* Quiz or Survey
                 */
                availability: 'assess',

                /* Whether you came from the QuestionBank
                 */
                questionBank: false,

                /* An element containing all the available configuration options
                 */
                configuration: '#configuration',

                /* Custom validation rules to use
                 */
                customValidationRules: function() {
                }
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Add the minimum number of options to choose from
         */
        function addDefaultOptions() {
            if (!$(config.answerCollectionList).find('[data-input]').length) {
                for (var i = 0; i < config.minOptions; i++) {
                    $(config.answerCollectionList).append($('[data-clone] [data-input]').clone());
                }
            }

            /* Update letter indexes
             */
            refreshList();
        }

        /**
         * Reindex letter index
         */
        function refreshList() {
            var i = 1;

            /* Only reindex options that have been added
             * (ignore the clone)
             */
            $(config.answerCollectionList).find('[data-input]').each(function(index, element) {
                $(element).find('[data-answer-code-representation]').html($.fn.evaluation.findLetterIndex(i) + ')');
                $(element).find('[data-answer-code-value]').val(i);

                /* Update checkbox id and label target, due to custom styles
                 */
                var id = 'answer-option-'+i;

                $(element).find('[data-answer-code-representation]').parent('label').attr('for', id);
                $(element).find('[data-answer-option]').attr('id', id);

                i++;
            });
        }

        /**
         * Add a new answer option
         */
        function addNewAnswerOption() {
            $(document).on('click', '[data-add-answer]', function(event) {
                event.preventDefault();

                /* Obey limit
                 */
                var allInputs = $(config.answerCollectionList).find('[data-input]');

                if (allInputs.length < config.maxOptions) {
                    $(config.answerCollectionList).append($('[data-clone] [data-input]').clone());

                    refreshList();
                }

                return false;
            });
        }

        /**
         * Remove an answer option
         */
        function removeAnswerOption() {
            $(document).on('click', '[data-delete]', function(event) {
                event.preventDefault();

                /* Only remove when greater than three
                 */
                var allInputs = $(config.answerCollectionList).find('[data-input]');

                if (allInputs.length > config.minOptions) {
                    $(this).parents('[data-input]').remove();

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
            $(config.element).submit(function() {
                // update underlying textarea before submit validation
                tinyMCE.triggerSave();
            }).validate({
                submitHandler: function() {
                    var theForm = $(config.element);

                    /* Form preprocessing
                     */
                    var answerCollection = [];
                    tinyMCE.triggerSave();

                    var content = tinyMCE.activeEditor.getContent(); // get the content
                    $('#question').val(content); // put it in the textarea

                    /* Collate possible answers and their correctness status
                     */
                    $.each($(config.answerCollectionList).find('[data-input]'), function(index, element) {
                        var elem = $(element);

                        var description = $.trim(elem.find('[data-answer-description]').val());
                        var code = $.trim(elem.find('[data-answer-code-value]').val());

                        /* Ignore empty answers that weren't removed
                         */
                        if ((description != '') && (code != '')) {
                            answerCollection.push({
                                description: description,
                                value: code,
                                correct: elem.find('[data-answer-option]').prop('checked')
                            });
                        }
                    });

                    /* Collate the usual fields containing ids.
                     * Collate the checkboxes too but append all the possibilities as well!
                     *
                     * Common data
                     */
                    var data = {
                        question: content,
                        sequence: theForm.find('input[name=sequence]').val(),
                        allAnswers: JSON.stringify(answerCollection),
                        configuration: JSON.stringify($(config.configuration).find('input, textarea, select').serializeArray())
                    };

                    /* Quiz-specific data
                     */
                    if (config.availability == 'assess') {
                        data.marks = theForm.find('input[name=marks]').val();
                    }

                    /* Redirect url
                     */
                    var redirect = '';

                    /* Create in Quiz/Survey
                     */
                    if (config.mode == 'create') {
                        data.type_id = theForm.find('select[name=type_id]').val(),
                        data.section_id = theForm.find('input[name=section_id]').val();

                        redirect = '/' + config.availability + '/question/create/' + data.section_id + '/create';
                    }
                    else {
                        /* Modify in Quiz/Survey
                         */
                        if ((typeof config.questionBank == "undefined") || (config.questionBank === false)) {
                            data.type_id = theForm.find('input[name=type_id]').val(),
                            data.section_id = theForm.find('input[name=section_id]').val(),
                            data.question_id = theForm.find('input[name=question_id]').val(),

                            redirect = '/' + config.availability + '/question/modify/' + data.question_id;
                        }
                        /* Modify directly in the Question Bank
                         */
                        else {
                            data.type_id = theForm.find('input[name=type_id]').val(),
                            data.question_bank_id = theForm.find('input[name=question_bank_id]').val();

                            redirect = config.questionBank.url;
                        }
                    }

                    /* Submit via POST and redirect to question creation screen upon done
                     */
                    $.post(theForm.attr('action'), data, function(responseText) {
                        window.location = redirect;
                    });

                    tinyMCE.triggerSave();

                    return false;
                },

                rules: {
                    question: {
                        required: true,
                        minlength: 2
                    },
                    marks: {
                        required: true,
                        minlength: 1,
                        greaterThanZero:true
                    },
                    'answer[]': {
                        somethingChecked: true
                    },
                    'answerDescription[]': {
                        emptyInputs: true
                    }
                },

                errorPlacement: function(error, element) {

                    /* If the error has to do with the entire list,
                     * spawn the error at the bottom
                     * http://stackoverflow.com/questions/1318076/jquery-hasattr-checking-to-see-if-there-is-an-attribute-on-an-element
                     */
                    var hasCorrectAnswer = $(element).attr('data-correct-answer');
                    var hasAllDescriptors = $(element).attr('data-answer-description');

                    if ((typeof hasCorrectAnswer !== typeof undefined) && (hasCorrectAnswer !== false)) {
                        error.insertAfter(element.closest(config.answerCollectionList));
                    }
                    else if ((typeof hasAllDescriptors !== typeof undefined) && (hasAllDescriptors !== false)) {
                        error.insertAfter(element.closest(config.answerCollectionList));
                    }
                    /* If the error was found on the Marks field, just leave it
                     * to spawn naturally
                     *
                     * If the error was found on one of the answer options,
                     * spawn it naturally
                     */
                    else {
                        error.insertAfter(element);
                    }
                }
            });
        }
    }
}(jQuery));