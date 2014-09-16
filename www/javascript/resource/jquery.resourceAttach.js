/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
;
(function ($, Dropzone, window, document, undefined) {

    var pluginName = 'resourceAttach',
        noop = function () {},
        defaults = {

            // our options
            trigger: null,
            detachDelay: 200,
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

            // pass through to various implementations
            allowedFiles: null,
            multi: false,

            // callbacks
            onSelect: noop
        };


    function ResourceAttach(element, options) {
        var self, opt, azone;

        this.element = element;
        this.options = $.extend(true, {}, defaults, options);
        this.modal   = null;
        this.tpl     = $(this.element).find('[data-template]').detach();

        self  = this;
        opt   = self.options;
        azone = $(this.element);

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


        // wrap ourselves around an instance of dropzone
        azone.dropzone({
            url: opt.url,
            paramName: opt.paramName,
            clickable: false,
            maxFiles: opt.multi ? null : 1,
            acceptedFiles: opt.allowedFiles,
            thumbnailWidth: opt.thumbnailSize,
            thumbnailHeight: opt.thumbnailSize,
            dictDefaultMessage: opt.dictDefaultMessage,
            previewsContainer: opt.previewsContainerSelector,
            previewTemplate: this.tpl.html(),

            fallback: function () {
                var mk  = opt.fallbackButtonMarkup || '<a href="#" data-attach-fallback class="btn up-arrow float-left"><span></span>Attach</a>',
                    btn = $(mk);

                // mimic dropzone behaviour
                azone.addClass('dz-unsupported');

                $(opt.dropzoneSelector).addClass('clearfix').append(btn);
                opt.trigger = opt.fallbackButtonSelector;
            },

            init: function () {
                var content = $('<span/>').html(opt.dictDefaultMessage);
                $(opt.dropzoneSelector).append(content);

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
                        $(file.previewElement)
                            .find('input[data-az-attachment]')
                            .val(data.hash);
                        self.attach(data);
                    }
                    catch (err) {
                        // noop
                    }

                });

                this.on('error', function (file, errMsg, xhr) {
                    if (xhr === undefined) {
                        this.removeFile(file);
                    }
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
        $(window).on('message', { self: this }, function (e) {
            var message,
                self     = e.data.self,
                explorer = self.modal.find('li.browsing').find('div.explorer');

            try {
                message = JSON.parse(e.originalEvent.data);
            } catch (err) {
                message = e.originalEvent.data;
            }

            if (message.text) {
                explorer.attr('data-text', message.text);
            } else if (message.data) {
                explorer.removeAttr('data-text');
                self.attach(message.data);
            }
        });

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

            if(self.dropzone) {
                self.dropzone.files.push(file);

                dbtn.one('click', function (e) {
                    self.dropzone.removeFile(file);
                });
            }
            else {
                dbtn.one('click', function (e) {
                    self.detach(file);
                });
            }


        });

        if(this.dropzone) {
            this.dropzone._updateMaxFilesReachedClass();
        }

        // prepare the dialog in the background
        this.build();
    }


    ResourceAttach.prototype.build = function () {
        var self = this,
            body = $('body'),
            qString;

        // if my modal already exists, destroy it
        this.destroy();

        this.modal = $('<div/>');

        body.append(this.modal);

        qString = 'multi=' + (this.options.multi ? '1' : '0');

        if (this.options.allowedFiles) {
           qString += '&mime=' + this.options.allowedFiles;
        }

        this.modal.load(this.options.source, qString, function () {
            // anything you need to do upon completion of loading
        });

        this.modal.dialog({
            width:     this.options.width,
            height:    this.options.height,
            title:     this.options.title,
            modal:     this.options.modal,
            resizable: this.options.resizable,
            autoOpen:  false,
            close: function (e) {
                self.build();
            }
        });

        $(this.element).on('click', this.options.trigger, function (e) {
            self.open();
        });
    };


    ResourceAttach.prototype.open = function () {
        if (this.modal == null) {
            this.build();
        }

        this.modal.dialog('open');
    };


    ResourceAttach.prototype.close = function () {
        this.modal.dialog('close');
    };


    ResourceAttach.prototype.destroy = function () {
        if (this.modal) {
            this.modal.dialog('close');
            this.modal.dialog('destroy');
            this.modal.detach();
            this.modal = null;
        }
    };


    ResourceAttach.prototype.attach = function (list) {
        var i, data, self = this;

        this.close();

        for (i = 0; i < list.length; ++i) {
            data = {
                name: list[i].title,
                type: list[i].mimeType,
                size: list[i].filesize,
                hash: list[i].hash,
            };

            if (this.dropzone) {
                this.dropzone.addFile(data);
            }
            else {
                data.previewElement = $(this.tpl.html());

                this.renderFile(data);

                $(this.element)
                    .find(this.options.previewsContainerSelector)
                    .append(data.previewElement);

                data.previewElement.find('[data-dz-remove]').on('click', function () {
                    self.detach(this);
                });
            }
        }

    };


    ResourceAttach.prototype.detach = function (elem) {
        //noinspection AssignmentToFunctionParameterJS
        elem = $(elem);
        elem.addClass(this.options.detachClass);

        if (this.dropzone) {
            this.dropzone._updateMaxFilesReachedClass();
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


    $.fn[pluginName] = function (options) {
        return this.each(function () {
            var ra;
            if (!$.data(this, 'plugin_' + pluginName)) {
                ra = new ResourceAttach(this, options);
                $.data(this, 'plugin_' + pluginName, ra);
            }
        });
    };

}(jQuery, Dropzone, window, document));