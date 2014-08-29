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
            parallelUploads: 1,

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
        self = this;

        // setup standard vars
        this.element = element;
        this.options = $.extend({}, defaults, options);

        // setup private vars
        this._defaults = defaults;
        this._name = plugin;
        this._wrap = $(this.options.wrap);
        this._previewsContainer = $(this.options.previewContainer);

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
        $(window).on('dragenter dragend drop dragleave mouseenter', this.detect);

        $(this.element).dropzone({
            clickable: this.options.clickable,
            previewsContainer: this.options.previewsContainer,
            createImageThumbnails: this.options.createImageThumbnails,
            parallelUploads: this.options.parallelUploads,

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
        if (drag) {
            self._wrap.addClass(self.options.wrapClass);
        }
        else {
            self._wrap.removeClass(self.options.wrapClass);
        }
    }

    FileDrop.prototype.createTemplate = function(tpl) {
        var t = $(tpl);

        var isself = t.find('.dz-preview').length ? false : true;

        e = isself ? t : t.find('.dz-preview');

        e.addClass('dz-file-preview')
            .append($('<div class="dz-details" />')
                .append($('<div class="dz-filename" />').html('<span data-dz-name></span>'))
                .append('<img width="16" height="16" src="/images/resources/ajax.gif" />'))
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

}(jQuery, window, document, Dropzone));

// since we're using fileDrop, don't autodiscover dropzones
Dropzone.autoDiscover = false;

