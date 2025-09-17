(function () {
    /** already a thing? don't make another instance */
    if (typeof jQuery.fn.markTypeGradingScheme !== "undefined") {
        return;
    }

    const configEntries = {};

    /**
     * markTypeGradingScheme
     *
     * This pivots on a Mark Type select dropdown, displaying Total Marks and
     * Grading Scheme ui elements accordingly.
     *
     * @param options
     */
    $.fn.markTypeGradingScheme = function (options) {
        options = options || {};

        const config = init(options, $(this));
        configEntries[config.context] = config;

        /* Set up event listeners
         */
        config.markTypeDropdown.on("change", function () {
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
        config.markTypeDropdown.trigger("change");

        /* Populate the grade scale modal on scheme change
         */
        config.gradingSchemeSelect.change(populateScaleModal);

        /* Handle sync mode
         */
        if (config.sync) {
            const syncSwitch = document.querySelector(config.sync.switchSelector);
            syncSwitch.addEventListener("change", () => {
                syncEntriesIfNeeded();
            });

            // Whenever any field value changes, sync the entries if we need to
            config.markTypeDropdown.on("change", syncEntriesIfNeeded);
            config.maxMarkInput.on("input", syncEntriesIfNeeded);
            config.gradingSchemeSelect.on("change", syncEntriesIfNeeded);

            // If this is the first load then apply the initial state
            syncEntriesIfNeeded();
        }

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
                context: "",
                sync: null,

                /* These const correspond to backend consts
                 */
                markTypeTotalMark: null,
                markTypeLetterGrade: null,
            };

            /* Derive some selectors
             */
            let context = "";

            if (options.context && options.context.length) {
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
                markTypeDropdown: $("#" + context + "markType"),

                /* The label text for the mark type dropdown
                 */
                markTypeLabel: $("#" + context + "markTypeLabel"),

                /* The tooltip for the mark type dropdown when sync is disabled
                 */
                markTypeTooltip: $("#" + context + "markTypeTooltip"),

                /* The tooltip for the mark type dropdown when sync is enabled
                 */
                markTypeSyncTooltip: $("#" + context + "syncMarkTypeTooltip"),

                /* A container for the Total Mark elements
                 */
                maxMarkDiv: $('[data-max-mark="' + context + '"]'),

                /* The maximum mark input field
                 */
                maxMarkInput: $("#" + context + "maxMark"),

                /* A container for the Grading Scheme elements
                 */
                gradingSchemeDiv: $('[data-grading-scheme="' + context + '"]'),

                /* Grading Scheme select element
                 */
                gradingSchemeSelect: $("#" + context + "gradingScheme"),

                /* Title in the modal
                 */
                gradingSchemeTitle: $("#" + context + "gradeScaleTitle"),

                /* Main body in the modal
                 */
                gradingSchemeBody: $("#" + context + "gradeScaleDesc"),
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
            let schemeName = config.gradingSchemeSelect.find(":selected").text(),
                schemeId = config.gradingSchemeSelect.val(),
                schemeTemplate = $("[data-grading-scale=" + config.context + schemeId + "]").html();

            // Populate the modal title with scheme name
            config.gradingSchemeTitle.text(schemeName);

            // Populate the modal body with the scheme table
            config.gradingSchemeBody.html(schemeTemplate);
        }

        /**
         * Sync the current values of all fields that are in sync mode with their source
         * if the switch is unchecked (i.e. only if the source field is in control)
         *
         * Also applies the correct state of which dropdowns are showing, and the correct label texts
         */
        function syncEntriesIfNeeded() {
            if (config.sync === null) {
                return;
            }

            // If this is set to sync with another field, hide the dropdown if the switch is off
            // OFF means to sync, ON means to allow dual mark types
            const shouldSync = !document.querySelector(config.sync.switchSelector).checked;

            if (config.sync.syncWith !== null) {
                config.markTypeDropdown[0].closest(".row").style.display = shouldSync ? "none" : "";
            }

            // Show the correct tooltip based on the switch state
            if (shouldSync) {
                config.markTypeTooltip.hide();
                config.markTypeSyncTooltip.show();
            } else {
                config.markTypeTooltip.show();
                config.markTypeSyncTooltip.hide();
            }

            config.markTypeLabel.text(shouldSync ? config.sync.syncMarkTypeOffLabel : config.sync.syncMarkTypeOnLabel);

            if (!shouldSync) {
                return; // the switch is enabled, so the fields shouldn't be sync'd
            }

            // If this was triggered by a change in a target entry, ignore it (otherwise we'll get an infinite loop)
            // or skip it if the source config can't be found (perhaps if the fields loaded in the incorrect order?)
            const sourceConfig = configEntries[config.sync.syncWith] ?? null;
            if (sourceConfig === null) {
                return;
            }

            // Find all target entries that should sync with this source entry
            const targetEntries = Object.values(configEntries).filter(
                (entry) => entry.sync && entry.sync.syncWith === sourceConfig.context,
            );

            // Apply the source values to all target entries
            targetEntries.forEach((targetConfig) => {
                targetConfig.markTypeDropdown.val(sourceConfig.markTypeDropdown.val());
                targetConfig.maxMarkInput.val(sourceConfig.maxMarkInput.val());
                targetConfig.gradingSchemeSelect.val(sourceConfig.gradingSchemeSelect.val());

                targetConfig.markTypeDropdown.trigger("change");
                targetConfig.maxMarkInput.trigger("input");
                targetConfig.gradingSchemeSelect.trigger("change");
            });
        }
    };
})(jQuery);
