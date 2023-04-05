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

                        const overlayLoaderHTML = `
                        <div data-loader-overlay style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.8); z-index: 10000">
                            <div class="preview loader" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);"></div>
                        </div>`;

                        document.body.insertAdjacentHTML('beforeend', overlayLoaderHTML);

                        var href = $(this).attr(config.attribute);
                        var url = config.baseUrl + "/" + encodeURIComponent(href);

                        $.get(url, function (response) {
                            document.querySelector("[data-loader-overlay]")?.remove()

                            if (response.type == "redirect") {
                                window.location.href = response.fragment;
                            } else if (response.type == "image") {
                                document.dispatchEvent(new CustomEvent("IMAGE-LIGHTBOX__CREATE", {
                                    detail: {
                                        targetKey: "preview-image",
                                        options: {
                                            dynamic: true,
                                            dynamicEl: [
                                                {
                                                    src: response.fragment
                                                }
                                            ]
                                        },
                                        onCreated: function (gallery) {
                                            gallery.openGallery();
                                        }
                                    }
                                }));
                            } else if (response.type == "iframe") {
                                document.dispatchEvent(new CustomEvent("IMAGE-LIGHTBOX__CREATE", {
                                    detail: {
                                        targetKey: "preview-iframe",
                                        options: {
                                            dynamic: true,
                                            dynamicEl: [
                                                {
                                                    src: response.fragment,
                                                    iframe: true
                                                }
                                            ],
                                            iframeWidth: "80%",
                                            iframeHeight: "80%",
                                        },
                                        onCreated : function(gallery) {
                                            gallery.openGallery();
                                        }
                                    }
                                }));
                            } else {
                                document.dispatchEvent(new CustomEvent("IMAGE-LIGHTBOX__CREATE", {
                                    detail: {
                                        targetKey: "preview-custom",
                                        options: {
                                            dynamic: true,
                                            dynamicEl: [
                                                {
                                                    src: "/static/images/foundation/preloader.gif"
                                                }
                                            ],
                                        },
                                        onCreated: function (gallery) {
                                            // The fragment sent back can contain scripts, so it is important that we
                                            // append it to the DOM in a way that will execute the scripts as well.
                                            const fragmentDiv = document.createRange().createContextualFragment(
                                                `<div class="lg-video-cont" style="width:80%;max-width:100%;max-height:100%;">
                                                    <div class="preview fragment">${response.fragment}</div>
                                                </div>`
                                            );

                                            // This is a slight workaround to display the content properly, as LightGallery
                                            // has no way of adding custom HTML to the lightbox, this replaces the preloader
                                            // image with the content we want to load.
                                            gallery.el.addEventListener("lgAfterOpen", function () {
                                                if (!gallery.$inner.selector.querySelector(".lg-current")) { return; }

                                                gallery.$inner.selector.querySelector(".lg-current").innerHTML = "";
                                                gallery.$inner.selector.querySelector(".lg-current").appendChild(fragmentDiv);
                                            });

                                            gallery.openGallery();
                                        }
                                    }
                                }));
                            }
                        }).fail(function () {
                            // Did something break?
                            // Just pretend this preview never happened.
                            document.querySelector("[data-loader-overlay]")?.remove()
                            window.location.href = href;
                        });
                    }
                });
        }
    };
})(jQuery);
