(function ($) {
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
