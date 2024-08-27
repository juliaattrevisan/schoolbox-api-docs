/**!
 * Created by matt.day on 29/08/14.
 */
(function ($, window, document, Dropzone, undefined) {
    var plugin = "fileDrop",
        drag = 0,
        pending = 0,
        noop = function () {},
        defaults = {
            // filedrop detection options
            wrap: "html > body",
            wrapClass: "dragging",
            showIfEmpty: true,

            // dropzone options we choose to expose
            clickable: false,
            previewsContainer: ".dropzone",
            createImageThumbnails: false,
            thumbnailWidth: 100,
            thumbnailHeight: 100,
            parallelUploads: 1,
            acceptedFiles: null,
            fallback: noop,
            forceFallback: false,
            maxFilesize: 2048,
            paramName: "file",

            // custom handling for dropzone
            navWarning: "You still have files uploading, which will be cancelled if you leave this page.",
            tplWrap: '<div class="dz-preview" />',
            previewTemplate: null,

            // componentry
            refreshSelector: null,
            refreshUrl: "/homepage/configForm.php",
            refreshData: {},
            refreshTimeout: 2000,

            // dropzone callbacks
            addedfile: noop,
            sending: noop,
            complete: noop,
            queuecomplete: noop,
            contentReloaded: noop,

            uploadprogress: function (file, progress, bytesSent) {
                var el = $(file.previewElement);
                el.find("div.dz-filename").addClass("started");
                el.find("span.dz-upload").css("width", parseInt(progress) + "%");
            },

            success: function (file, response) {
                var el = $(file.previewElement);

                el.attr("data-file", response.id)
                    .removeClass("dz-processing")
                    .find("span.dz-upload")
                    .addClass("complete");
            },

            error: function (file, response) {
                $(file.previewElement)
                    .removeClass("dz-processing")
                    .find("span.dz-upload")
                    .addClass("error")
                    .attr("title", response.error);
            },

            // dropzone dictionary
            dictDefaultMessage: "",
            dictFallbackMessage: "",

            // Reload component content on file upload. Set the default to `true` for now as file component is the only one that's using this as of the moment.
            reloadContent: true,
        };

    function FileDrop(element, options) {
        // sigh
        var self = this,
            acceptedMime = null,
            dataAccepts;

        // setup standard vars
        this.element = element;
        this.options = $.extend({}, defaults, options);

        // setup private vars
        this._defaults = defaults;
        this._name = plugin;
        this._wrap = $(this.options.wrap);
        this._dragging = [];
        this._tt = null;

        // only allow global window listeners once
        if (!$.data(window, "listening_" + plugin)) {
            //noinspection FunctionWithInconsistentReturnsJS
            window.onbeforeunload = function () {
                if (pending > 0) {
                    return self.options.navWarning;
                }
            };

            // detect dragstart
            $(window).on("dragenter", function (e) {
                drag = 2;
            });

            // detect dragstop
            $(window).on("dragend drop dragleave mouseenter", function (e) {
                if (drag > 0) {
                    --drag;
                }
            });

            $.data(window, "listening_" + plugin, true);
        }

        // add THIS detect to the window listener
        $(window).on("dragenter dragend drop dragleave mouseenter", function (e) {
            // Add a delay to ensure that dragleave event has processed
            // before drop event
            setTimeout(function () {
                self.detect(e);
            }, 100);
        });

        if (this.options.acceptedFiles) {
            acceptedMime = this.options.acceptedFiles;
        } else {
            dataAccepts = $(this.element).attr("data-accepts");
            if (dataAccepts) {
                acceptedMime = dataAccepts + "/*";
            }
        }

        $(document).on("click", ".dz-error [data-close]", function () {
            $(this).parents("li, [data-dz-template-item]").fadeOut("slow");
        });

        var myDropzone = new Dropzone(this.element, {
            // $(this.element).dropzone({
            clickable: this.options.clickable,
            previewsContainer: this.options.previewsContainer,
            createImageThumbnails: this.options.createImageThumbnails,
            thumbnailWidth: this.options.thumbnailWidth,
            thumbnailHeight: this.options.thumbnailHeight,
            parallelUploads: this.options.parallelUploads,
            acceptedFiles: acceptedMime,
            fallback: this.options.fallback,
            forceFallback: this.options.forceFallback,
            maxFilesize: this.options.maxFilesize,
            paramName: this.options.paramName,
            timeout: 1800000,
            dictDefaultMessage: this.options.dictDefaultMessage,
            dictFallbackMessage: this.options.dictFallbackMessage,
            previewTemplate: this.options.previewTemplate
                ? $(this.options.previewTemplate).detach().html()
                : this.createTemplate(this.options.tplWrap),

            // expose dropzone methods
            init: function () {
                self.checkState();

                //fileDrop instances should not be clickable, as they weren't before
                //it appears an update to DropZone has added in a <button class='dz-button>
                //and the options.clickable property is not being considered, so the
                //button still appears despite it being set to false on all fileDrop instances
                if (!this.options.clickable) {
                    const dzMessage = this.element.querySelector(".dz-message");
                    const dzButton = this.element.querySelector(".dz-button");
                    if (dzButton !== null) {
                        dzMessage.removeChild(dzButton);
                        dzMessage.textContent = dzButton.textContent;
                    }
                }

                this.on("sending", function () {
                    self.options.sending.apply(this, arguments);
                });
                this.on("uploadprogress", function () {
                    self.options.uploadprogress.apply(this, arguments);
                });
                this.on("success", function () {
                    self.options.success.apply(this, arguments);
                });
                this.on("error", function () {
                    self.options.error.apply(this, arguments);
                });

                this.on("addedfile", function (file) {
                    ++pending;
                    if (self.options.refreshSelector) {
                        window.clearTimeout(self._tt);
                    }
                    self.options.addedfile.apply(this, [file]);
                    self.checkState();
                });

                this.on("complete", function (file) {
                    --pending;
                    self.options.complete.apply(this, [file]);
                });

                this.on("queuecomplete", function () {
                    if (self.options.refreshSelector) {
                        self._tt = window.setTimeout(function () {
                            $.post(self.options.refreshUrl, self.options.refreshData).done(function (data) {
                                /**
                                 * Everytime a file is being uploaded, the component gets refreshed.
                                 * With that when there's an error, the message gets automatically dismissed as well.
                                 *
                                 * With [SA-5443] for file components, we'll make the error messages is dismissible by the user.
                                 */
                                if (self.options.reloadContent) {
                                    $(self.options.refreshSelector).empty().append($(data).children());
                                    self.options.contentReloaded.apply(this, arguments);
                                }
                            });
                        }, self.options.refreshTimeout);
                    }

                    self.options.queuecomplete.apply(this, arguments);
                });

                // affect global state
                this.on("dragenter", function (file) {
                    drag = 2;
                });
            },
        });
    }

    FileDrop.prototype.checkState = function () {
        var containerEmpty = !$(this.options.previewsContainer).children().length,
            e = $(this.options.refreshSelector);

        if (containerEmpty && this.options.dictDefaultMessage && this.options.showIfEmpty) {
            e.addClass("dragging");
        } else {
            e.removeClass("dragging");
        }
    };

    FileDrop.prototype.detect = function (event) {
        event.preventDefault();

        var i,
            self = this,
            dragging = [],
            native = event.originalEvent;

        // read global state
        if (!drag) {
            // no drag operation in progress
            self._wrap.removeClass(self.options.wrapClass);

            for (i = 0; i < self._dragging.length; ++i) {
                self._wrap.removeClass(self._dragging[i]);
            }

            self._dragging = [];
            return;
        }

        // we're dragging files, so add the 'dragging' class
        self._wrap.addClass(self.options.wrapClass);

        // no datatransfer object available, ignore this
        if (typeof native.dataTransfer == "undefined" || native.dataTransfer == null) {
            return;
        }

        var dt = native.dataTransfer;

        // don't got files, ignore this
        if (!containsFiles(native)) {
            return;
        }

        // drop event, will be picked up by dropzone
        if (!(typeof dt.files == "undefined" || dt.files == null || dt.files.length == 0)) {
            return;
        }

        // attempt to read the items array, if data not available, defer back to generic
        var fileItems = dt.items || [{ type: "unknown/mime" }],
            mime = "";

        // update wrapper with reference to what we're dragging around
        for (i = 0; i < fileItems.length || 0; ++i) {
            mime = "mime-" + (fileItems[i].type.match(/[^/]+/) || "binary");
            self._wrap.addClass(mime);
            dragging.push(mime);
        }

        self._dragging = dragging;
    };

    FileDrop.prototype.createTemplate = function (tpl) {
        var e = $(this.element);

        if (e.find(".dz-preview").length) {
            var template = outerHTML(e.find(".dz-preview")[0]);
            e.html("");
            return template;
        }

        var t = $(tpl);

        var isself = t.find(".dz-preview").length ? false : true;

        e = isself ? t : t.find(".dz-preview");

        // Hardcoding the static URL will be good enough.
        // I can see no place which actually creates a template instead of
        // having it be passed in.
        e.addClass("dz-file-preview")
            .append(
                $('<div class="dz-details" />')
                    .append($('<div class="dz-filename" />').html("<span data-dz-name></span>"))
                    .append(
                        '<img class="dz-pending" width="16" height="16" src="/static/images/foundation/preloader.gif" />',
                    ),
            )
            .append($('<div class="dz-progress" />').html('<span class="dz-upload"></span>'));

        if (isself) {
            return outerHTML(t.append(e)[0]);
        }

        return outerHTML(t[0]);
    };

    /**
     * FileDrop can only be applied to forms, and only once per form
     *
     * @param options
     * @returns {each|*}
     */
    $.fn[plugin] = function (options) {
        if (!Dropzone.isBrowserSupported()) {
            return this;
        }

        return this.each(function () {
            if (!$.data(this, "plugin_" + plugin) && $(this).is("form")) {
                $.data(this, "plugin_" + plugin, new FileDrop(this, options));
            }
        });
    };

    /**
     * Internal helper function
     *
     * @see http://stackoverflow.com/a/3819589
     * @param node
     * @returns {string}
     */
    function outerHTML(node) {
        // if IE, Chrome take the internal method otherwise build one
        return (
            node.outerHTML ||
            (function (n) {
                var div = document.createElement("div"),
                    h;
                div.appendChild(n.cloneNode(true));
                h = div.innerHTML;
                div = null;
                return h;
            })(node)
        );
    }

    /**
     * @see http://css-tricks.com/snippets/javascript/test-if-dragenterdragover-event-contains-files/
     * @param event
     * @returns {boolean}
     */
    function containsFiles(event) {
        // event.dataTransfer.types should be an array, but in IE seems to be something else
        if (event.dataTransfer.types && event.dataTransfer.types.length) {
            for (var i = 0; i < event.dataTransfer.types.length; i++) {
                if (event.dataTransfer.types[i] == "Files") {
                    return true;
                }
            }
        }
        return false;
    }
})(jQuery, window, document, Dropzone);

// since we're using fileDrop, don't autodiscover dropzones
Dropzone.autoDiscover = false;
