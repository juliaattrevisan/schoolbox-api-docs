/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
;
// It's dangerous to go alone. Take this.
// IE9+ polyfill: https://developer.mozilla.org/en-US/docs/Web/API/CustomEvent
(function () {
    if (typeof window.CustomEvent == 'undefined') {
        function CustomEvent ( event, params ) {
            params = params || { bubbles: false, cancelable: false, detail: undefined };
            var evt = document.createEvent( 'CustomEvent' );
            evt.initCustomEvent( event, params.bubbles, params.cancelable, params.detail );
            return evt;
        }

        CustomEvent.prototype = window.Event.prototype;

        window.CustomEvent = CustomEvent;
    }
})();

// get a fix on your IE9 origin
if(typeof window.location.origin == 'undefined') {
    window.location.origin = window.location.protocol + '//' + window.location.hostname;
}

(function ($, Dropzone, window, document, undefined) {

    var pluginName     = 'resourceAttach',
        singletonModal = null,
        modalCache     = {},
        noop           = function () {},
        listening      = false,
        defaults       = {

            // our options
            trigger: null,
            detachDelay: 800,
            detachSelector: 'p.actions a.delete',
            detachClass: 'detach',
            attachmentTag: 'li',
            fallbackButtonMarkup: null,
            fallbackButtonSelector: '[data-attach-fallback]',

            // modal options
            title: null,
            width: 800,
            height: 600,
            resizable: false,
            modal: true,
            source: '/resources/attachment',

            // dropzone options
            paramName: 'file',
            dropzoneSelector: '.dropzone',
            url: '#',
            dictDefaultMessage: null,
            previewsContainerSelector: 'ul.az-list',
            thumbnailSize: 64,
            tplOverride: null,
            maxFilesize: 2048,

            // pass through to various implementations
            allowedFiles: null,
            multi: false,

            // callbacks
            onSelect: noop
        };


    function ResourceAttach(element, options) {
        var self, opt, azone, dzone, prompt;

        this.element = element;
        this.options = $.extend(true, {}, defaults, options);
        this.tpl     = this.options.tplOverride || $(this.element).find('[data-template]').detach();

        self  = this;
        opt   = self.options;
        azone = $(this.element);
        dzone = azone.find(opt.dropzoneSelector).html('');

        if (opt.title == null) {
            opt.title = 'Select file' + (opt.multi ? 's' : '') + ' to attach';
        }

        if (opt.trigger == null) {
            opt.trigger = this.element;
        }

        azone.on('click', opt.detachSelector, function (e) {
            var li = $(this).closest(opt.attachmentTag);
            self.detach(li);
            e.preventDefault();
        });

        prompt = 'Click or drag here to ' + opt.dictDefaultMessage.trim().charAt(0).toLowerCase() + opt.dictDefaultMessage.substr(1);

        // wrap ourselves around an instance of dropzone
        azone.dropzone({
            url: opt.url,
            paramName: opt.paramName,
            clickable: false,
            maxFiles: opt.multi ? null : 1,
            maxFilesize: opt.maxFilesize,
            acceptedFiles: opt.allowedFiles === '*/*' ? null : opt.allowedFiles,
            thumbnailWidth: opt.thumbnailSize,
            thumbnailHeight: opt.thumbnailSize,
            dictDefaultMessage: prompt,
            previewsContainer: $(this.element).find(opt.previewsContainerSelector)[0],
            previewTemplate: this.tpl.html(),

            fallback: function () {
                var mk  = opt.fallbackButtonMarkup || '<a href="#" data-attach-fallback class="button icon-attach">' + opt.dictDefaultMessage + '</a>',
                    btn = $(mk);

                // mimic dropzone behaviour
                azone.addClass('dz-unsupported');

                dzone.addClass('clearfix').append(btn);
                opt.trigger = opt.fallbackButtonSelector;

                self.fallbackFiles = [];
            },

            init: function () {
                dzone.append($('<span/>').html(prompt));

                // *goes crosseyed*
                self.dropzone = this;

                this.on('addedfile', function (file) {
                    self.renderFile({
                        previewElement: file.previewElement,
                        type: file.type
                    });
                });

                this.on('success', function (file, response) {
                    var data;

                    try {
                        data = JSON.parse(response);
                    }
                    catch (err) {
                        data = response;
                    }

                    $(file.previewElement)
                        .find('input[data-az-attachment]')
                        .val(data.hash);
                    self.attach(data);


                });

                this.on('error', function (file, errMsg, xhr) {
                    self.rejectFile(file, errMsg, this);
                });

            },

            removedfile: function (file) {
                self.detach(file.previewElement);
            },

            maxfilesexceeded: function (file) {
                this.removeFile(file);
            },

            accept: function (file, done) {
                // no hash yet: treat as normal dz upload
                if(file.hash === undefined) {
                    done();
                    return;
                }

                // has hash: this means we've already got its storage reference
                self.renderFile(file);

                // trick dropzone into treating this file as though it were successfully uploaded
                file.accepted = true;
                file.status   = Dropzone.SUCCESS;

                this._updateMaxFilesReachedClass();
            }
        });

        // for iframe communication
        if(!listening) {
            $(window).on('message', function (e) {
                var message,
                    modal    = singletonModal,
                    self     = modal.data('rtn'),
                    explorer = modal.find('li.browsing').find('div.explorer');

                try {
                    message = JSON.parse(e.originalEvent.data);
                } catch (err) {
                    message = e.originalEvent.data;
                }

                if (message.text) {
                    explorer.attr('data-text', message.text);
                } else if (message.data) {
                    explorer.removeAttr('data-text');
                    var elems = self.attach(message.data);
                    $(elems).each(function() { this.addClass('dz-success') });
                }
            });
            listening = true;
        }

        // check for existing attachments and push them into the dropzone's list
        azone.find(opt.previewsContainerSelector).find(opt.attachmentTag).each(function() {
            var elem = $(this),
                hash = elem.find('[data-az-attachment]').val(),
                name = elem.find('[data-dz-name]').text(),
                dbtn = elem.find('[data-dz-remove]'),
                file = {
                    accepted: true,
                    status: Dropzone.SUCCESS,
                    previewElement: this,
                    name: name,
                    hash: hash
                };

            if (self.dropzone) {
                self.dropzone.files.push(file);

                dbtn.one('click', function (e) {
                    self.dropzone.removeFile(file);
                });
            } else {
                self.fallbackFiles.push(file);

                dbtn.one('click', function (e) {
                    var ff = self.fallbackFiles;
                    ff.splice(ff.indexOf(file), 1);
                    self.detach(file);
                });
            }
        });

        if (this.dropzone) {
            this.dropzone._updateMaxFilesReachedClass();
        }
        else {
            this.fallbackMaxFilesReached();
        }

        // cache the content for this reveal modal
        this.cacheModal();

        $(this.element).on('click', this.options.trigger, function (e) {
            e.preventDefault();
            self.open();
        });
    }


    /**
     *
     * @param {File|object}   file
     * @param {string|object} errMsg
     */
    ResourceAttach.prototype.rejectFile = function (file, errMsg) {
        var data, element, t,
            errList = $(this.element).children('.az-error-list');

        if (typeof errMsg == 'string') {
            try {
                data = JSON.parse(errMsg).hash.split(':')[1];
            } catch (e) {
                data = errMsg;
            }
        } else {
            data = errMsg.hash.split(':')[1];
        }

        element = $(file.previewElement).clone().addClass('dz-error');

        element.appendTo(errList);

        element.children('.dz-progress, input').detach();
        t = element.children('[data-dz-name]').text();
        element.children('[data-dz-name]').text(t + ' failed: ' + data).attr('title', data);

        if (this.dropzone) {
            this.dropzone.removeFile(file);
        }
    };


    ResourceAttach.prototype.getQueryString = function () {
        var qString = 'multi=' + (this.options.multi ? '1' : '0');

        if (this.options.allowedFiles) {
            qString += '&mime=' + this.options.allowedFiles;
        }

        return qString;
    };


    ResourceAttach.prototype.getCacheKey = function () {

        return this.options.source + '?' + this.getQueryString();
    };


    ResourceAttach.prototype.cacheModal = function (callback) {

        var key  = this.getCacheKey(),
            self = this;

        if ('undefined' == typeof modalCache[key]) {
            modalCache[key] = $.get(this.options.source, this.getQueryString()).promise();
        }

        modalCache[key].done(function (data) {
            // ensure we retain ResourceAttache context
            self.build.call(self, data);
        });

        if (callback) {
            modalCache[key].done(function (data) {
                // ensure we retain ResourceAttache context
                callback.call(self, data);
            });
        }
    };


    ResourceAttach.prototype.build = function (data) {

        if (singletonModal) {
            // you've got a modal; don't try to make another one
            return;
        }

        var body = $('body');

        // if the modal already exists, destroy it
        this.destroy();

        singletonModal = $('<div data-reveal class="reveal-modal file-picker" />')
            .data('content', $.parseHTML(data, document, true))
            .data('rtn', this);

        body.append(singletonModal);

        // reinit
        $(document)
            .foundation('reveal', 'reflow')
            .on('closed.fndtn.reveal', '[data-reveal]', function () {
                singletonModal.detach();
                singletonModal = null;
            });


        singletonModal.on('click', '.cancel[role=button]', function (e) {
            window.top.postMessage(JSON.stringify({ data: [] }), location.origin);
        });
    };


    ResourceAttach.prototype.open = function () {
        if (singletonModal == null) {
            // this will construct a modal VIA the cache
            this.cacheModal(function () {
                singletonModal.html(singletonModal.data('content'))
                              .foundation('reveal', 'open');

            });
        } else {
            // ensure the return points to the right element
            singletonModal.data('rtn', this)
                .html(singletonModal.data('content'))
                .foundation('reveal', 'open');
        }
    };


    ResourceAttach.prototype.close = function () {
        if (singletonModal) {
            singletonModal.foundation('reveal', 'close');
        }
    };


    ResourceAttach.prototype.destroy = function () {
        if (singletonModal) {
            singletonModal.foundation('reveal', 'close');
        }
    };


    ResourceAttach.prototype.attach = function (list) {
        var i, data, elems = [], self = this;

        this.close();

        for (i = 0; i < list.length; ++i) {
            data = {
                name: list[i].title,
                type: list[i].mimeType,
                size: list[i].filesize,
                hash: list[i].hash
            };

            if (this.dropzone) {
                this.dropzone.addFile(data);
                elems.push($(data.previewElement));
            }
            else {
                data.previewElement = $(this.tpl.html());

                this.renderFile(data);

                $(this.element)
                    .find(this.options.previewsContainerSelector)
                    .append(data.previewElement);

                data.previewElement.find('[data-dz-remove]').on('click', function () {
                    var ff = self.fallbackFiles;
                    ff.splice(ff.indexOf(data), 1);
                    self.detach(this);
                });

                elems.push(data.previewElement);

                this.fallbackFiles.push(data);

                this.fallbackMaxFilesReached();
            }

            // now the file has been added...if it's buggy, take it out again
            if (data.hash.split(':')[0] == 'exception') {
                this.rejectFile(data, data);
            }
        }

        return elems;
    };


    ResourceAttach.prototype.detach = function (elem) {
        //noinspection AssignmentToFunctionParameterJS
        elem = $(elem);
        elem.addClass(this.options.detachClass);

        if (this.dropzone) {
            this.dropzone._updateMaxFilesReachedClass();
        } else {
            this.fallbackMaxFilesReached();
        }

        setTimeout(function () {
            elem.remove();
        }, this.options.detachDelay);
    };


    ResourceAttach.prototype.renderFile = function (file) {
        var elem;

        if(!file.previewElement) {
            return;
        }

        elem = $(file.previewElement);

        // render whatever variables are available
        if (file.name) {
            elem.find('[data-dz-name]').html(file.name);
        }
        if (file.hash) {
            elem.find('input[data-az-attachment]').val(file.hash);
        }
        if (file.type) {
            elem.find('img[data-dz-thumbnail]').attr('src','/ajax/mime/' + file.type);
        }
    };


    ResourceAttach.prototype.fallbackMaxFilesReached = function () {
        // if we accept multi, there's no limit to check
        if (this.options.multi) {
            return;
        }
        if (this.fallbackFiles.length) {
            $(this.element).addClass('dz-max-files-reached');
        } else {
            $(this.element).removeClass('dz-max-files-reached');
        }
    };


    $.fn[pluginName] = function (options) {
        return this.each(function () {
            var ra;
            if (!$.data(this, 'plugin_' + pluginName)) {
                ra = new ResourceAttach(this, options);
                $.data(this, 'plugin_' + pluginName, ra);
            }
        });
    };


    $.fn[pluginName + 'Clone'] = function (source) {
        return this.each(function () {
            if (source.data('plugin_' + pluginName)) {
                // grab the options from the existing source and push them into a new instance
                var ra = source.data('plugin_' + pluginName);
                var options = ra.options;
                options.tplOverride = ra.tpl.clone();
                $(this)[pluginName](options);
            }
        });
    }

}(jQuery, Dropzone, window, document));