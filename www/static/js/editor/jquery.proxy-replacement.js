import { getConfigForVideo } from "@js/mediaelementplayer/mepUtils.ts";

(function () {
    /**
     * Replace all placeholders with html elements
     * By default this replaces everything in #content, our main container
     */
    $.fn.schoolboxResource_ProxyReplacement = function (identifier) {
        /* Replace all the placeholders with html fragments that a media player can understand
         */
        $.fn.schoolboxResource_ReplaceAll($(identifier));

        /* Initialise the media player on this container
         */
        $.fn.schoolboxResource_InitialiseMediaElementPlayer(identifier);
    };

    /**
     * Mass-replace all media placeholders
     *
     * @param selector  a jquery selection. This element contains all the placeholders
     *                  that should be replaced with a media player
     * @return int      the number of things that got replaced
     */
    $.fn.schoolboxResource_ReplaceAll = function (selector) {
        let count = 0;

        $.each(selector.find("[data-schoolboxresource-type]"), function (index, element) {
            // Don't re-replace something that already has a replacement
            if ($(element).next(`[data-schoolboxmedia-replacement-container]`).length > 0) {
                return;
            }

            // Don't replace if the placeholder is inside a CKEditor, otherwise it becomes invisible
            if ($(element).closest(".ck.ck-editor__editable").length > 0) {
                return;
            }

            const replaced = $.fn.schoolboxResource_ReplaceOne(element);
            if (replaced) {
                count++;
            }
        });

        return count;
    };

    /**
     * Mass-restore all media placeholders that were hidden by
     * `$.fn.schoolboxResource_ReplaceAll`.
     *
     * This will remove the replacements, but they ought to be restored by
     * calling `$.fn.schoolboxResource_ReplaceAll` on the element again.
     *
     * @param selector
     * @return {Number} the number of placeholders which got restored
     */
    $.fn.schoolboxResource_RestoreAllPlaceholders = function (selector) {
        var count = 0;
        selector.find("[data-schoolboxmedia-replacement-container]").each(function () {
            if ($.fn.schoolboxResource_RestorePlaceholder(this)) {
                count++;
            }
        });
        return count;
    };

    /**
     * Apply all the placeholders that were found in element.
     *
     * This used to replace the placeholders completely: the inline editor
     * requires that the placeholder continues to exist (as the replacement
     * breaks the inline editor), so now it hides the placeholder and adds the
     * replacement after it.
     *
     * @param element
     */
    $.fn.schoolboxResource_ReplaceOne = function (element) {
        var replacement = $.fn.schoolboxResource_GenerateReplacement(element);

        if (replacement !== false) {
            $(element).css("display", "none").after(replacement);
            return true;
        }

        return false;
    };

    /**
     * Restores the placeholder that was replaced by
     * `$.fn.schoolboxResource_ReplaceOne`.
     *
     * This will remove the replacement, and its video player.
     *
     * @param element
     * @returns {boolean}
     */
    $.fn.schoolboxResource_RestorePlaceholder = function (element) {
        var $element = $(element),
            $placeholder = $element.prev("[data-schoolboxresource-type]");
        if ($placeholder.length) {
            // Does this actually have a placeholder to restore?
            $placeholder.css("display", "");
            $element.find(".mejs__container").each(function () {
                var playerId = this.id;
                if (playerId && mejs.players && mejs.players[playerId]) {
                    mejs.players[playerId].remove();
                }
            });
            element.remove();
            return true;
        }
        return false;
    };

    /**
     * Get parameters from DOM
     * @param element
     * @returns {{thumbnail: *, maxwidth: string, classes: *, width: *, style: *, source: *, type: *, height: *, fullwidth: *}}
     */
    $.fn.schoolboxResource_GetParams = function (element) {
        var e = $(element);

        return {
            style: e.attr("style") !== undefined ? e.attr("style") : "",
            width: e.attr("data-schoolboxresource-width") !== undefined ? e.attr("data-schoolboxresource-width") : "",
            height:
                e.attr("data-schoolboxresource-height") !== undefined ? e.attr("data-schoolboxresource-height") : "",
            thumbnail:
                e.attr("data-schoolboxresource-thumbnail") !== undefined
                    ? e.attr("data-schoolboxresource-thumbnail")
                    : "",
            classes: e.attr("class") !== undefined ? e.attr("class") : "",
            source:
                e.attr("data-schoolboxresource-source") !== undefined ? e.attr("data-schoolboxresource-source") : "",
            type: e.attr("data-schoolboxresource-type") !== undefined ? e.attr("data-schoolboxresource-type") : "",
            fullwidth:
                e.attr("data-schoolboxresource-fullwidth") !== undefined
                    ? e.attr("data-schoolboxresource-fullwidth")
                    : "",
            maxwidth:
                e.attr("data-schoolboxresource-fullwidth") === "true"
                    ? "100%"
                    : e.attr("data-schoolboxresource-width") + "px",
        };
    };

    /**
     * Generate html compatible with media player
     *
     * @param element a jquery selection that we must examine
     * @returns {string|boolean}
     */
    $.fn.schoolboxResource_GenerateReplacement = function (element) {
        var params = $.fn.schoolboxResource_GetParams(element);

        var style = params["style"];

        var width = params["width"];
        var height = params["height"];
        var thumbnail = params["thumbnail"];
        var classes = params["classes"];
        var source = params["source"];
        var fullwidth = params["fullwidth"];
        var type = params["type"];
        var maxwidth = width + "px";

        if (fullwidth === "true") {
            maxwidth = "100%";
        }

        if (typeof source == "undefined") {
            source = "";
        }
        source = source.replace("type=browse", "type=sbfs").replace("type=store", "type=storage");

        let mediaContainer = document.createElement("div");
        mediaContainer.setAttribute("data-schoolboxmedia-replacement-container", "");

        if (type === "video") {
            let mediaElement = document.createElement("video");
            mediaElement.setAttribute("width", width);
            mediaElement.setAttribute("height", height);
            mediaElement.setAttribute("data-schoolboxmedia-replacement", "");
            mediaElement.setAttribute("controls", "");
            mediaElement.setAttribute("preload", "none");
            mediaElement.setAttribute("poster", thumbnail);
            mediaElement.setAttribute("class", classes);
            mediaElement.style.width = "100%";
            mediaElement.style.height = "100%";

            let mediaSource = document.createElement("source");
            mediaSource.setAttribute("src", source);
            mediaSource.setAttribute("type", "video/mp4");

            mediaElement.appendChild(mediaSource);
            mediaContainer.style.maxWidth = maxwidth;
            mediaContainer.appendChild(mediaElement);

            return mediaContainer;
        } else if (type === "audio") {
            let mediaElement = document.createElement("audio");
            mediaElement.setAttribute("data-schoolboxmedia-replacement", "");
            mediaElement.setAttribute("controls", "");
            mediaElement.setAttribute("preload", "none");
            mediaElement.setAttribute("style", style);
            mediaElement.style.width = "100%";
            mediaElement.style.height = "40px";
            mediaElement.style.border = "0px";

            let mediaSource = document.createElement("source");
            mediaSource.setAttribute("src", source);
            mediaSource.setAttribute("type", "audio/mpeg");

            mediaElement.appendChild(mediaSource);
            mediaContainer.appendChild(mediaElement);

            return mediaContainer;
        }

        return false;
    };

    /**
     * Replace all media players with mejs
     * @param selector
     */
    $.fn.schoolboxResource_InitialiseMediaElementPlayer = function (selector) {
        $(selector)
            .find("[data-schoolboxmedia-replacement]")
            .each(function (index, element) {
                const $element = $(element);

                // Override height and width set directly on the [data-schoolboxmedia-replacement-container] to 'auto'.
                // This is necessary to avoid a problem that would otherwise occur when the size of embedded CKEditor
                // videos has been set to something other than 'original' or 'full-width'.
                //
                // To demonstrate the purpose of this fix:
                //  1. Embed a video in a CKEditor text field.
                //  2. Edit the CKEditor text field, and click on the video.  Increase the size of the video by dragging its
                //     corner.  Set the width of the video to more than 640 pixels.
                //  3. Save the text CKEditor field, and return to the non-edit view of the document.
                //  4. Decrease the width of your browser window to its minimum, and reload the page.
                //     Expected behaviour:
                //      * The video dimensions will be constrained by the available space, and no scroll bars will appear.
                //  5. Now comment out the line below, and refresh the page.
                //     Expected undesired behaviour:
                //      * The video will be too big for the available space, so some of it will be hidden, and scroll bars
                //        will appear on the video's container.
                $element.closest("[data-schoolboxmedia-replacement-container]").css({ width: "auto", height: "auto" });

                const $componentDiv = $element.closest("[data-collapsable]");
                const componentDiv = $componentDiv.length > 0 ? $componentDiv[0] : null;

                if (componentDiv !== null && componentDiv.getAttribute("data-collapse-state") === "collapsed") {
                    // The video is inside a component that is currently collapsed (eg. on homepage).
                    // A mediaelementplayer cannot be initialised on a hidden <video> element, so we must wait until it becomes visible.

                    const onExpandStarted = () => {
                        $element.mediaelementplayer(getConfigForVideo());
                        // This event listener only needs to be run on the first event, so remove it now that its work is done.
                        componentDiv.removeEventListener("expandStarted.collapsable", onExpandStarted);
                    };

                    // Add event listener for when the component becomes visible.
                    componentDiv.addEventListener("expandStarted.collapsable", onExpandStarted);
                } else {
                    $element.mediaelementplayer(getConfigForVideo());
                }
            });
    };
})(jQuery);
