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
                                                mobileSettings: {
                                                    showCloseIcon: true,
                                                },
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
                                                mobileSettings: {
                                                    showCloseIcon: true,
                                                },
                                            },
                                            onCreated: function (gallery) {
                                                gallery.openGallery();
                                            },
                                        },
                                    }),
                                );
                            } else if (response.type == "pdf") {
                                // Workaround CORS issues by forcing the PDF.js viewer to be loaded from Schoolbox's
                                // host, rather than from the CDN.
                                let viewerUrl = new URL('/static/js/pdf/pdfjs/web/viewer.html', window.location.origin),
                                    pdfUrl    = new URL(response.fragment, window.location.origin);
                                viewerUrl.searchParams.set('file', pdfUrl);
                                const src = viewerUrl.toString();
                                document.dispatchEvent(
                                    new CustomEvent("IMAGE-LIGHTBOX__CREATE", {
                                        detail: {
                                            targetKey: "preview-iframe",
                                            options: {
                                                autoplay: false,
                                                counter: false, // hide as could be confusing with the pages of the pdf conflicting with the gallery counter
                                                download: false, // download would be of the viewer, not the actual pdf
                                                dynamic: true,
                                                dynamicEl: [
                                                    {
                                                        src: src,
                                                        iframe: true,
                                                    },
                                                ],
                                                enableDrag: false, // ensure doesn't cause any conflict with dragging in the pdf viewer
                                                enableSwipe: false, // ensure doesn't cause any conflict with swiping in the pdf viewer
                                                iframeWidth: "100%",
                                                iframeHeight: "100%",
                                                mobileSettings: {
                                                    showCloseIcon: true,
                                                },
                                            },
                                            onCreated: function (gallery) {
                                                gallery.el.addEventListener("lgAfterOpen", function () {
                                                    const iframe = gallery.$inner.selector.querySelector("iframe");
                                                    if (iframe) {
                                                        // Hide the editor mode buttons in the PDF.js viewer
                                                        iframe.onload = function () {
                                                            iframe.contentWindow.document.getElementById(
                                                                "editorModeButtons",
                                                            ).style.display = "none";
                                                        };
                                                    }
                                                });
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
                                                mobileSettings: {
                                                    showCloseIcon: true,
                                                },
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
