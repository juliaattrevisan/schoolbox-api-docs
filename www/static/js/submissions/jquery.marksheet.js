/**
 * This is only used by the rubric markbook
 */
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
     * @param {boolean} shouldShowDrafts - whether to show drafts in the download
     */
    const changeDownloadHref = function (shouldShowDrafts) {
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
                changeDownloadHref(true);
            } else {
                hideDrafts();
                changeDownloadHref(false);
            }
        });
    };
})(jQuery);
