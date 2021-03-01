(function() {

    /**
     * Plugin
     *
     * @param options
     */
    $.fn.importFeedback = function(options) {
        options = options || {};

        let config = init(options, $(this));

        setupValidation();
        setupCloseListener();
        setupAssignationListener();


        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            let defaults = {
                element: element,
                feedbackModal: $('#importFeedbackModal'),
                feedbackInput: $('#importFeedbackInput')
            };

            return $.extend(defaults, options);
        }


        /**
         * Set up validation on this element
         */
        function setupValidation() {
            $(config.element).alaressValidate({
                rules: {
                    importFeedbackType: "required",
                    importFeedbackInput: "required"
                },
                onfocusout: false,
                onkeyup: false
            });
        }


        /**
         * Close modal via Cancel button
         */
        function setupCloseListener() {
            $(config.element).find('[data-cancel]').on('click', function(event) {
                event.preventDefault();
                $(config.feedbackModal).foundation('reveal', 'close');
            });
        }


        /**
         * Condense multiple tabs into one tab
         * Multiple tabs occurs when you split-select columns in excel
         * such as selecting column A and column D
         *
         * @return array
         */
        function combineSplitColumns(rows) {
            let i, condensed = [];

            for (i = 0; i < rows.length; i++) {
                condensed[i] = rows[i].replace(/\t\t+/g, "\t");
            }

            return condensed;
        }


        /**
         * Look ahead to combine multiple lines into the previous row's comment field
         *
         * @return array
         */
        function combineMultilineComments(rows) {
            let i, j, preprocessed = [];

            for (i = 0, j = 0; i < rows.length; i++) {
                let current = rows[i];

                if (current.split("\t").length > 1) {

                    /* If the condensed element has more than 1 element in it
                     * then it's an actual new row of data; therefore we can just
                     * add it to the preprocessed stack
                     */
                    j++;
                    preprocessed[j] = current;
                } else {

                    /* Otherwise the condensed element is a broken-up comment and this
                     * row should be combined into preprocessed's current element
                     */
                    let val = "\n" + current;

                    if (preprocessed[j] === undefined) {

                        /* First time this array element had data
                         */
                        preprocessed[j] = val;
                    } else {

                        /* There was something else here; append the current val
                         */
                        preprocessed[j] += val;
                    }
                }
            }

            return preprocessed;
        }


        /**
         * Excel copy-paste encapsulate multiline comments in double quotes
         *
         * Presume that if the comment starts and ends with a double quote
         * that it came from this place and remove them, then replace the
         * original comment with the new one
         *
         * @return array
         */
        function removeEncapsulatingQuotes(rows) {
            let i, preprocessed = [];

            for (i = 0; i < rows.length; i++) {
                if (rows[i] === undefined) {
                    continue;
                }

                let row = rows[i].split("\t");
                let comment = $.trim(row[row.length-1]);

                if (comment[0] === '"' && comment[comment.length-1] === '"') {
                    row[row.length-1] = comment.replace(/^"|"$/g, '');
                }

                preprocessed[i] = row.join("\t");
            }

            return preprocessed;
        }


        /**
         * Decide how to assign the csv data
         *
         * @return function
         */
        function selectAssignation() {

            /* Find out which prototype to use
             */
            let dataFormat = $(config.element).find('input[name="importFeedbackType"]:checked').val();

            /* Ignore any rows without a student identifier
             * Trigger autosave upon change for instant feedback on your feedback
             * Assign data in one of three slightly-different ways
             */
            let assign;

            if (dataFormat === 'mark-only') {
                assign = function(input, form) {
                    $(form).find('input[name="mark"], select[name="mark"]').val(input[1]).change();
                }
            } else if (dataFormat === 'comment-only') {
                assign = function(input, form) {
                    $(form).find('textarea[name="comment"]').val(input[1]).change();
                }
            } else {
                assign = function(input, form) {
                    $(form).find('input[name="mark"], select[name="mark"]').val(input[1]).change();
                    $(form).find('textarea[name="comment"]').val(input[2]).change();
                }
            }

            return assign;
        }


        /**
         * For every row, find the student id, mark and comments and
         * assign them to #mark-12345 and #comment-12345 as required
         *
         * @return void
         */
        function performAssignation(rows) {
            let i;

            /* Take the raw csv and fix all the problems with it
             */
            let preprocessed = combineSplitColumns(rows);
            preprocessed = combineMultilineComments(preprocessed);
            preprocessed = removeEncapsulatingQuotes(preprocessed);

            for (i = 0; i < preprocessed.length; i++) {
                if (preprocessed[i] === undefined) {
                    continue;
                }

                let input = preprocessed[i].split("\t");
                let id = $.trim(input[0]);

                if (id) {
                    selectAssignation()(input, $('#assessment-status [data-participant-external-id="'+id+'"] [data-feedback-form]'));
                }
            }
        }


        /**
         * Activate data assignation upon modal confirmation
         *
         * @return void
         */
        function setupAssignationListener() {
            $(config.element).find('[data-submit]').on('click', function(event) {
                event.preventDefault();

                /* If valid, start processing data
                 */
                if ($(config.element).valid()) {
                    let rows = $(config.feedbackInput).val().split("\n");

                    /* Assign csv data
                     */
                    performAssignation(rows);

                    /* Close the modal
                     */
                    $(config.feedbackModal).foundation('reveal', 'close');
                }
            });
        }
    }
}(jQuery));
