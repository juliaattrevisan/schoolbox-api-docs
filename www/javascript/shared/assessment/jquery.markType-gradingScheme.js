(function() {

    /**
     * markTypeGradingScheme
     *
     * This pivots on a Mark Type select dropdown, displaying Total Marks and
     * Grading Scheme ui elements accordingly.
     *
     * @param options
     */
    $.fn.markTypeGradingScheme = function(options) {
        options = options || {};

        let config = init(options, $(this));

        /* Set up event listeners
         */
        config.markTypeDropdown.on('change', function () {
            if ($(this).val() === config.markTypeTotalMark) {
                showMaxMark();
            } else {
                hideMaxMark();
            }

            if ($(this).val() === config.markTypeLetterGrade) {
                showGradingScheme();
            } else {
                hideGradingScheme();
            }
        });

        /* Initialise mark type dropdown
         */
        config.markTypeDropdown.trigger('change');

        /* Populate the grade scale modal on scheme change
         */
        config.gradingSchemeSelect.change(populateScaleModal);

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
                context: '',

                /* These const correspond to backend consts
                 */
                markTypeTotalMark: null,
                markTypeLetterGrade: null
            };

            /* Derive some selectors
             */
            let context = '';

            if (options.context.length) {
                context = options.context;
            }

            /* Finally get to use a derived object literal
             */
            let derived = {

                /* Enforce type safety
                 */
                markTypeTotalMark: parseInt(options.markTypeTotalMark),
                markTypeLetterGrade: parseInt(options.markTypeLetterGrade),

                /* Mark Type dropdown dictates what other elements become available
                 */
                markTypeDropdown: $('#' + context + 'markType'),

                /* A container for the Total Mark elements
                 */
                maxMarkDiv: $('[data-max-mark="' + context + '"]'),

                /* A container for the Grading Scheme elements
                 */
                gradingSchemeDiv: $('[data-grading-scheme="' + context + '"]'),

                /* Grading Scheme select element
                 */
                gradingSchemeSelect: $('#' + context + 'gradingScheme'),

                /* Title in the modal
                 */
                gradingSchemeTitle: $('#' + context + 'gradeScaleTitle'),

                /* Main body in the modal
                 */
                gradingSchemeBody: $('#' + context + 'gradeScaleDesc')
            };

            return $.extend(defaults, derived, options);
        }

        /**
         * Show max mark
         */
        function showMaxMark() {
            config.maxMarkDiv.show();
        }

        /**
         * Hide max mark
         */
        function hideMaxMark() {
            config.maxMarkDiv.hide();
        }

        /**
         * Show grading scheme after populating the grade scale modal with the default scheme selected
         */
        function showGradingScheme() {
            populateScaleModal();

            config.gradingSchemeDiv.show();
        }

        /**
         * Hide grading scheme
         */
        function hideGradingScheme() {
            config.gradingSchemeDiv.hide();
        }

        /**
         * Put a title and grade scale into a modal based on the currently selected scheme
         */
        function populateScaleModal() {
            let schemeName = config.gradingSchemeSelect.find(':selected').text(),
                schemeId = config.gradingSchemeSelect.val(),
                schemeTemplate = $('[data-grading-scale=' + config.context + schemeId +']').html();

            // Populate the modal title with scheme name
            config.gradingSchemeTitle.text(schemeName);

            // Populate the modal body with the scheme table
            config.gradingSchemeBody.html(schemeTemplate);
        }
    }
}(jQuery));
