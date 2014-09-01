/**!
 * Created by matt.day on 29/08/14.
 */
;(function($, window, document, Dropzone, undefined) {

    var plugin = 'fileDrop',
        drag = pending = 0,
        noop = function() {},
        defaults = {
            // filedrop detection options
            wrap: 'html > body',
            wrapClass: 'dragging',

            // dropzone options we choose to expose
            clickable: false,
            previewsContainer: '.dropzone',
            createImageThumbnails: false,
            thumbnailWidth: 100,
            thumbnailHeight: 100,
            parallelUploads: 1,
            acceptedFiles: null,
            fallback: noop,
            forceFallback: false,

            // custom handling for dropzone
            navWarning: "You still have files uploading, which will be cancelled if you leave this page.",
            tplWrap: '<div class="dz-preview" />',

            // dropzone callbacks
            addedfile: noop,
            sending: noop,
            complete: noop,

            uploadprogress: function (file, progress, bytesSent) {
                var el = $(file.previewElement);
                el.find('div.dz-filename').addClass('started');
                el.find('span.dz-upload').css('width', parseInt(progress) + '%');
            },

            success: function (file, response) {
                var el = $(file.previewElement);

                el.attr('data-file', response.id)
                    .removeClass('dz-processing')
                    .find('span.dz-upload')
                    .addClass('complete');
            },

            error: function (file, response) {
                $(file.previewElement).removeClass('dz-processing').find('span.dz-upload').addClass('error').attr('title', response.error);
            },


            // dropzone dictionary
            dictDefaultMessage: '',
            dictFallbackMessage: ''

        };

    function FileDrop(element, options) {

        // sigh
        var self = this;

        // setup standard vars
        this.element = element;
        this.options = $.extend({}, defaults, options);

        // setup private vars
        this._defaults = defaults;
        this._name = plugin;
        this._wrap = $(this.options.wrap);
        this._previewsContainer = $(this.options.previewContainer);
        this._dragging = [];

        // only allow global window listeners once
        if(!$.data(window, 'listening_'+plugin)) {

            window.onbeforeunload = function() {
                // check if there are outstanding uploads
                return pending <= 0 ? null : self.options.navWarning;
            }

            // detect dragstart
            $(window).on('dragenter', function(e) {
                drag = 2;
            });

            // detect dragstop
            $(window).on('dragend drop dragleave mouseenter', function(e) {
                if(drag > 0) {
                    --drag;
                }
            });

            $.data(window, 'listening_'+plugin, true);
        }

        // add THIS detect to the window listener
        $(window).on('dragenter dragend drop dragleave mouseenter', null, this, this.detect);

        $(this.element).dropzone({
            clickable: this.options.clickable,
            previewsContainer: this.options.previewsContainer,
            createImageThumbnails: this.options.createImageThumbnails,
            thumbnailWidth: this.options.thumbnailWidth,
            thumbnailHeight: this.options.thumbnailHeight,
            parallelUploads: this.options.parallelUploads,
            acceptedFiles: this.options.acceptedFiles,
            fallback: this.options.fallback,
            forceFallback: this.options.forceFallback,

            dictDefaultMessage: this.options.dictDefaultMessage,
            dictFallbackMessage: this.options.dictFallbackMessage,
            previewTemplate: this.createTemplate(this.options.tplWrap),

            // expose dropzone methods
            init: function() {
                this.on('sending',        function() { self.options.sending.apply(this, arguments) });
                this.on('uploadprogress', function() { self.options.uploadprogress.apply(this, arguments); });
                this.on('success',        function() { self.options.success.apply(this, arguments); });
                this.on('error',          function() { self.options.error.apply(this, arguments); });

                this.on('addedfile', function(file) {
                    ++pending;
                    self.options.addedfile.apply(this, [file]);
                });

                this.on('complete', function(file) {
                    --pending;
                    self.options.complete.apply(this, [file])
                });

                // affect global state
                this.on('dragenter', function(file) {
                    drag = 2;
                });
            }
        });

    }

    FileDrop.prototype.detect = function (event) {
        event.preventDefault();

        var i,
            self     = event.data,
            dragging = [],
            native   = event.originalEvent;

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
        if (typeof native.dataTransfer == 'undefined' || native.dataTransfer == null) {
            return;
        }

        var dt = native.dataTransfer;

        // don't got files, ignore this
        if(!containsFiles(native)) {
            return;
        }

        // drop event, will be picked up by dropzone
        if(!(typeof dt.files == 'undefined' || dt.files == null || dt.files.length == 0)) {
            return;
        }

        // attempt to read the items array, if data not available, defer back to generic
        var fileItems = dt.items || [{type: 'unknown/mime'}],
            mime = '';

        // update wrapper with reference to what we're dragging around
        for (i = 0; i < fileItems.length || 0; ++i) {
            mime = 'mime-' + (fileItems[i].type.match(/[^//]+/) || 'binary');
            self._wrap.addClass(mime);
            dragging.push(mime);
        }

        self._dragging = dragging;

//        var accepts;
//
//        if (accepts = $(self.element).attr('data-accepts') || '') {
//            accepts = accepts.split(' ');
//        } else {
//            accepts = (self.options.acceptedFiles || '').split(',');
//        }
//
//        for (i = 0; i < accepts.length; ++i) {
//            accepts[i] = 'mime-' + accepts[i].match(/[^//]+/);
//        }


    }

    FileDrop.prototype.createTemplate = function(tpl) {
        var e = $(this.element);

        if(e.find('.dz-preview').length) {
            var template = outerHTML(e.find('.dz-preview')[0]);
            e.html('');
            return template;
        }

        var t = $(tpl);

        var isself = t.find('.dz-preview').length ? false : true;

        e = isself ? t : t.find('.dz-preview');

        e.addClass('dz-file-preview')
            .append($('<div class="dz-details" />')
                .append($('<div class="dz-filename" />').html('<span data-dz-name></span>'))
                .append('<img class="dz-pending" width="16" height="16" src="/images/resources/ajax.gif" />'))
            .append($('<div class="dz-progress" />').html('<span class="dz-upload"></span>'));

        if(isself) {
            return outerHTML(t.append(e)[0]);
        }

        return outerHTML(t[0]);
    }

    /**
     * FileDrop can only be applied to forms, and only once per form
     *
     * @param options
     * @returns {each|*}
     */
    $.fn[plugin] = function(options) {
        return this.each(function() {
            if(!$.data(this, 'plugin_' + plugin) && $(this).is('form')) {
                $.data(this, 'plugin_' + plugin, new FileDrop(this, options));
            }
        });
    }

    /**
     * Internal helper function
     *
     * @see http://stackoverflow.com/a/3819589
     * @param node
     * @returns {string}
     */
    function outerHTML(node) {
        // if IE, Chrome take the internal method otherwise build one
        return node.outerHTML || (
            function(n){
                var div = document.createElement('div'), h;
                div.appendChild( n.cloneNode(true) );
                h = div.innerHTML;
                div = null;
                return h;
            })(node);
    }

    /**
     * @see http://css-tricks.com/snippets/javascript/test-if-dragenterdragover-event-contains-files/
     * @param event
     * @returns {boolean}
     */
    function containsFiles(event) {
        if (event.dataTransfer.types) {
            for (var i = 0; i < event.dataTransfer.types.length; i++) {
                if (event.dataTransfer.types[i] == "Files") {
                    return true;
                }
            }
        }
        return false;
    }

}(jQuery, window, document, Dropzone));

// since we're using fileDrop, don't autodiscover dropzones
Dropzone.autoDiscover = false;

