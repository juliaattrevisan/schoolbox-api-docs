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
        var count = 0;

        $.each(selector.find("[data-schoolboxresource-type]"), function (index, element) {
            var replaced = $.fn.schoolboxResource_ReplaceOne(element);

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
        var maxwidth = width + "px;";

        if (fullwidth === "true") {
            maxwidth = "100%";
        }

        if (typeof source == "undefined") {
            source = "";
        }
        source = source.replace("type=browse", "type=sbfs").replace("type=store", "type=storage");

        if (type === "video") {
            return (
                '<div data-schoolboxmedia-replacement-container style="max-width:' +
                maxwidth +
                style +
                '">' +
                '<video width="' +
                width +
                '" height="' +
                height +
                '" data-schoolboxmedia-replacement controls preload="none" ' +
                'poster="' +
                thumbnail +
                '" class="' +
                classes +
                '" style="width:100%;height:100%;">' +
                '<source src="' +
                source +
                '" type="video/mp4">' +
                "</video>" +
                "</div>"
            );
        } else if (type === "audio") {
            return (
                "<div data-schoolboxmedia-replacement-container>" +
                '<audio data-schoolboxmedia-replacement controls preload="none" style="' +
                style +
                ';width:100%;height:40px;border:0px;">' +
                '<source src="' +
                source +
                '" type="audio/mpeg">' +
                "</audio>" +
                "</div>"
            );
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
                $(element).mediaelementplayer({
                    // If 'stretching' is set to 'auto', the video will not resize as the window resizes.
                    // If 'stretching' is set to 'responsive', we cannot enforce our desired aspect ratio, as the
                    // aspect ratio of the container will always match the aspect ratio of the video.  This is a shame,
                    // because if we used the 'responsive' mode, we wouldn't have to set up a 'resize' event handler,
                    // and resizing would be less janky.
                    //    To get the resizing and container aspect ratio to work as we desire, we must set 'stretching'
                    // to 'none', and create our own 'resize' event handler.
                    // See: https://github.com/mediaelement/mediaelement/blob/master/docs/api.md
                    stretching: "none",
                    /**
                     * @param media - the <mediaelementwrapper> DOM node (contains the <video> node)
                     * @param node - the <video> DOM node
                     * @param instance - the relevant instance of MediaElementPlayer
                     */
                    success: function (media, node, instance) {
                        // The aspect ratio for the video container is fixed for all videos and all screen sizes.  This is
                        // done so that portrait videos do not take up too much vertical space.
                        const aspectRatio = 16 / 9;

                        // This is the container for all mejs nodes (including <mediaelementwrapper>).
                        const $container = $(node).closest(".mejs__container");

                        if ($(node).hasClass("full-width")) {
                            $(node).css("width", "100%");
                            $container.css("height", Math.round($container.width() / aspectRatio) + "px");
                        }

                        // Ensure the video poster fills the container with the correct aspect ratio, and has black bars
                        // added on the top/bottom or sides if necessary instead of having a hidden part overflow the
                        // bounds of the container.
                        $container.find(".mejs__poster").attr("style", "");

                        // Ensure the video dimensions match the container dimensions when the video is playing.
                        // Documentation: https://github.com/mediaelement/mediaelement/blob/master/docs/usage.md
                        const containerW = $container.width();
                        const containerH = containerW / aspectRatio;
                        instance.setPlayerSize(containerW, containerH);

                        // Ensure the video container adjusts in size correctly as the window size changes (both when it
                        // is playing and not playing).
                        // If this function is removed (with the MediaElementPlayer 'stretching' option remaining as 'none'):
                        //  * downsizing the window from a large size will cause a horizontal scrollbar to appear on the
                        //    video container
                        //  * when upsizing the window from a small size, the video will remain small as the surrounding
                        //    space grows
                        //  * The video content size will not change as the window grows/shrinks.
                        $(window).resize(function () {
                            $container.css("width", "auto");
                            const containerW = $container.width();
                            const containerH = Math.round(containerW / aspectRatio);

                            $container.css("height", containerH + "px");
                            instance.setPlayerSize(containerW, containerH);
                        });
                    },
                });
            });
    };
})(jQuery);
