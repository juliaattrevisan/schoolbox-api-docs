(function ($) {
    /**
     * Controls for Averages
     */
    $.fn.marksheetAverages = function (options) {
        options = options || {};

        var config = init(options, $(this));

        hideTableRows();
        toggleTableRows();
        updateVisibility();

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

                /* When you click this element, rows are toggled
                 */
                averagesRowsSelector: "[data-toggle-averages]",

                /* These rows are shown when averagesRowsSelector is clicked
                 */
                averagesRows: "[data-sub-table-row]",

                /* Element containing the url for updating visibility of
                 * a Due Work/Quiz e.g. [data-visiblity-selector]
                 *
                 * data-visibility-selector, as an attribute, is also used
                 * to select a corresponding ajax feedback label
                 */
                visibilitySelector: "data-visibility-selector",

                /* Icon replacement
                 * Icon to show when you can expand/collapse
                 */
                icon: {
                    expand: "icon-down-arrow",
                    collapse: "icon-up-arrow",
                },
            };

            var derived = {};

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Hide all Averages rows
         */
        function hideTableRows() {
            $(config.averagesRows).hide();
        }

        /**
         * Toggle averages rows and change icon
         */
        function toggleTableRows() {
            $(config.averagesRowsSelector).click(function (event) {
                event.preventDefault();

                if ($(config.averagesRows).is(":visible")) {
                    $(this).addClass(config.icon.expand).removeClass(config.icon.collapse);

                    $("[data-sub-table-row]").hide();
                } else {
                    $(this).removeClass(config.icon.expand).addClass(config.icon.collapse);

                    $("[data-sub-table-row]").show();
                }
            });
        }

        /**
         * Change visibility value for a particular Due Work/Quiz
         * Ajaxes the new value silently
         */
        function updateVisibility() {
            $("[" + config.visibilitySelector + "]").change(function (event) {
                event.preventDefault();

                var self = $(this);

                $.get($(this).val(), function (data) {
                    var indicator = self.attr(config.visibilitySelector);

                    var label = $('[data-identifier="' + indicator + '"]');
                    label.text("Saved").css({ opacity: 1 }).removeClass("hide");
                    label.toastTimeout({ duration: 3000 });
                });
            });
        }
    };

    /**
     * Show draft data in markbook cells.
     */
    const showDrafts = function () {
        $("[data-draft-mark]").show();
        $("[data-actual-mark]").hide();
        $("[data-student-average]").each(function (idx, elem) {
            let $elem = $(elem);
            $elem.removeClass($elem.attr("data-off-class"));
            $elem.addClass($elem.attr("data-on-class"));
        });
    };

    /**
     * Hide draft data in markbook cells.
     */
    const hideDrafts = function () {
        $("[data-draft-mark]").hide();
        $("[data-actual-mark]").show();
        $("[data-student-average]").each(function (idx, elem) {
            let $elem = $(elem);
            $elem.removeClass($elem.attr("data-on-class"));
            $elem.addClass($elem.attr("data-off-class"));
        });
    };

    /**
     * Change the download href in any download markbook links.
     * @param {boolean|null} shouldShowDrafts - whether to show drafts in the download, null to keep the current state
     * @param {boolean|null} shouldShowDisplayMarks - whether to show display marks in the download, null to keep the current state
     */
    const changeDownloadHref = function (shouldShowDrafts, shouldShowDisplayMarks) {
        let downloadLinks = document.querySelectorAll("a[data-download-spreadsheet]");
        downloadLinks.forEach(function (downloadLink) {
            if (downloadLink && "href" in downloadLink) {
                let downloadUrl = new URL(downloadLink.href);

                // Update the showDrafts query parameter if necessary, keeping the current state if null
                if (shouldShowDrafts === true) {
                    downloadUrl.searchParams.set("showDrafts", "1");
                } else if (shouldShowDrafts === false) {
                    downloadUrl.searchParams.delete("showDrafts");
                }

                // Update the showDisplayMarks query parameter if necessary, keeping the current state if null
                if (shouldShowDisplayMarks === true) {
                    downloadUrl.searchParams.set("showDisplayMarks", "1");
                } else if (shouldShowDisplayMarks === false) {
                    downloadUrl.searchParams.delete("showDisplayMarks");
                }

                downloadLink.href = downloadUrl;
            }
        });
    };

    /**
     * Set up toggle on/off of drafts.
     *
     * On changing the show/hide drafts checkbox:
     * * show/hide drafts in the markbook
     * * change any `a[data-download-spreadsheet]` hrefs
     *
     * @param selector
     */
    $.marksheetToggleDrafts = function (selector) {
        $(document).on("change", selector, function () {
            if ($(this).prop("checked")) {
                showDrafts();
                changeDownloadHref(true, null);
            } else {
                hideDrafts();
                changeDownloadHref(false, null);
            }
        });
    };

    /**
     * Show the display (learner) mark in markbook cells and hide the instructor mark
     */
    function showDisplayMarks() {
        $("[data-learner-mark]").show();
        $("[data-instructor-mark]").hide();
    }

    /**
     * Hide the display (learner) mark in markbook cells and show the instructor mark
     */
    function hideDisplayMarks() {
        $("[data-learner-mark]").hide();
        $("[data-instructor-mark]").show();
    }

    /**
     * Set up toggle on/off of display marks.
     *
     * On changing the show/hide display marks checkbox:
     * * show/hide display marks in the markbook
     *
     * @param selector
     */
    $.marksheetToggleDisplayMarks = function (selector) {
        $(document).on("change", selector, function () {
            if ($(this).prop("checked")) {
                showDisplayMarks();
                changeDownloadHref(null, true);
            } else {
                hideDisplayMarks();
                changeDownloadHref(null, false);
            }
        });
    };
})(jQuery);
