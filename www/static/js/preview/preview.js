(function () {
    /**
     * Constructor
     *
     * @param options
     */
    $.fn.preview = function (options) {
        options = options || {};

        /* Store the config in this instance so private methods
         * can access it
         */
        var config = init(options, this);

        preview();

        /**
         * Initialise parameters
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                /* The element that activated preview
                 */
                element: element,

                /* The attribute to analyse
                 */
                attribute: "href",

                /* Base url where preview is located
                 */
                baseUrl: "/preview",
            };

            var derived = {};

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Preview
         *
         * Only apply to left-click events (though IE can't seem to
         * tell the difference)
         */
        function preview() {
            $(config.element)
                .off()
                .click(function (event) {
                    if (event.which <= 1) {
                        event.preventDefault();
                        document.dispatchEvent(new Event("OVERLAY_LOADER_SHOW"));

                        var href = $(this).attr(config.attribute);
                        var url = config.baseUrl + "/" + encodeURIComponent(href);

                        $.get(url, function (response) {
                            document.dispatchEvent(new Event("OVERLAY_LOADER_HIDE"));

                            // if response.fragment is null, use custom preview and show an error
                            if (response.fragment === null) {
                                response.type = "custom";
                                response.fragment =
                                    "There was a problem loading the preview, this file may have been deleted.";
                            }

                            if (response.type == "redirect") {
                                window.location.href = response.fragment;
                            } else if (response.type == "image") {
                                document.dispatchEvent(
                                    new CustomEvent("IMAGE-LIGHTBOX__CREATE", {
                                        detail: {
                                            targetKey: "preview-image",
                                            options: {
                                                dynamic: true,
                                                dynamicEl: [
                                                    {
                                                        src: response.fragment,
                                                    },
                                                ],
                                            },
                                            onCreated: function (gallery) {
                                                gallery.openGallery();
                                            },
                                        },
                                    }),
                                );
                            } else if (response.type == "iframe") {
                                document.dispatchEvent(
                                    new CustomEvent("IMAGE-LIGHTBOX__CREATE", {
                                        detail: {
                                            targetKey: "preview-iframe",
                                            options: {
                                                dynamic: true,
                                                dynamicEl: [
                                                    {
                                                        src: response.fragment,
                                                        iframe: true,
                                                    },
                                                ],
                                                iframeWidth: "80%",
                                                iframeHeight: "80%",
                                            },
                                            onCreated: function (gallery) {
                                                gallery.openGallery();
                                            },
                                        },
                                    }),
                                );
                            } else {
                                document.dispatchEvent(
                                    new CustomEvent("IMAGE-LIGHTBOX__CREATE", {
                                        detail: {
                                            targetKey: "preview-custom",
                                            options: {
                                                dynamic: true,
                                                dynamicEl: [
                                                    {
                                                        src: window.cdnPrefix + "/images/foundation/preloader.gif",
                                                    },
                                                ],
                                            },
                                            onCreated: function (gallery) {
                                                // The fragment sent back can contain scripts, so it is important that we
                                                // append it to the DOM in a way that will execute the scripts as well.
                                                const fragmentDiv = document.createRange().createContextualFragment(
                                                    `<div class="lg-video-cont" style="width:80%;max-width:100%;max-height:100%;">
                                                    <div class="preview fragment">${response.fragment}</div>
                                                </div>`,
                                                );

                                                // This is a slight workaround to display the content properly, as LightGallery
                                                // has no way of adding custom HTML to the lightbox, this replaces the preloader
                                                // image with the content we want to load.
                                                gallery.el.addEventListener("lgAfterOpen", function () {
                                                    if (!gallery.$inner.selector.querySelector(".lg-current")) {
                                                        return;
                                                    }

                                                    gallery.$inner.selector.querySelector(".lg-current").innerHTML = "";
                                                    gallery.$inner.selector
                                                        .querySelector(".lg-current")
                                                        .appendChild(fragmentDiv);

                                                    // As we have manipulated the contents of the lightbox ourselves, LightGallery
                                                    // will have no idea to change the download link to the correct URL, so we need
                                                    // to do it ourselves. Uses optional chaining to ensure the download link exists.
                                                    gallery.$toolbar.selector
                                                        .querySelector("[download]")
                                                        ?.setAttribute("href", href);
                                                });

                                                gallery.openGallery();
                                            },
                                        },
                                    }),
                                );
                            }
                        }).fail(function () {
                            // Did something break?
                            // Just pretend this preview never happened.
                            document.dispatchEvent(new Event("OVERLAY_LOADER_HIDE"));
                            window.location.href = href;
                        });
                    }
                });
        }
    };
})(jQuery);
