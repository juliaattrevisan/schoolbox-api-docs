/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
;(function ($, Dropzone, window, document, undefined) {

    var pluginName = 'resourceAttach',
        singletonModal = null,
        modalCache = {},
        noop = function () {
        },
        listening = false,
        defaults = {

            // our options
            trigger: null,
            detachDelay: 800,
            detachSelector: 'p.actions a.delete',
            detachClass: 'detach',
            attachmentTag: 'li',
            fallbackButtonMarkup: null,
            fallbackButtonSelector: '[data-attach-fallback]',
            iconClass: 'icon-attach',

            // modal options
            title: null,
            width: 800,
            height: 600,
            resizable: false,
            modal: true,
            useModal: true,
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
            capture: false,

            // pass through to various implementations
            allowedFiles: null,
            multi: false,
            useFolder: true,

            /* Optional Filesystem context
             * This is used by /resources/attachment.php to provide Filesystem options
             * denoted by this context as defined in Attachment/Context/Factory.php
             */
            fs: null,

            // callbacks
            onSelect: noop,
            onDetach: noop,

    };


    function ResourceAttach(element, options)
    {
        var self, opt, azone, dzone, prompt = '';

        this.element = element;
        this.options = $.extend(true, {}, defaults, options);
        this.tpl = this.options.tplOverride || $(this.element).find('[data-template]').detach();
        this.attachmentBrowserFilesystemsInnerHTML = ''; //to store the menu list options for this modal window drop zone, to insert into the singleton modal on click
        this.attachmentBrowserPanelContainerInnerHTML = ''; //to store the panel container options for this modal window drop zone, to insert into the singleton modal on click

        self = this;
        opt = self.options;
        azone = $(this.element);
        dzone = azone.find(opt.dropzoneSelector).html('').addClass(opt.iconClass);

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

            //will not be a function if the file has been prevented from uploading
            //for example if the file limit is 1, and the user dragged 2 files in,
            //the 2nd would error when its remove is selected
            if (typeof opt.onDetach === 'function') {
                opt.onDetach();
            }

        });

        if (opt.dictDefaultMessage) {
            // Is this a touch screen? Show a different prefix
            // (This is the same test Modernizr uses)
            var prefix = 'ontouchstart' in window
                ? 'Tap here to '
                : 'Click or drag here to ';
            prompt = prefix + opt.dictDefaultMessage.trim().charAt(0).toLowerCase() + opt.dictDefaultMessage.substr(1);
        }


        // wrap ourselves around an instance of dropzone
        azone.dropzone({
            url: opt.url,
            paramName: opt.paramName,
            // The dropzone is clickable if the modal is disabled
            clickable: opt.useModal ? false : dzone[0],
            maxFiles: opt.multi ? null : 1,
            maxFilesize: opt.maxFilesize,
            acceptedFiles: opt.allowedFiles === '*/*' ? null : opt.allowedFiles,
            thumbnailWidth: opt.thumbnailSize,
            thumbnailHeight: opt.thumbnailSize,
            dictDefaultMessage: prompt,
            previewsContainer: $(this.element).find(opt.previewsContainerSelector)[0],
            previewTemplate: this.tpl.html(),
            capture: opt.capture,
            timeout: 30 * 60 * 1000, // 30 minutes. Nginx does not connect to php-fpm until upload completes.

            fallback: function () {
                var mk = opt.fallbackButtonMarkup || '<a href="#" data-attach-fallback class="button icon-attach">' + opt.dictDefaultMessage + '</a>',
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
                    azone.trigger('addedfile.attachzone', [file]);
                });

                this.on('success', function (file, response) {

                    var data, inputValue;

                    try {
                        data = JSON.parse(response);
                    } catch (err) {
                        data = response;
                    }

                    // Use the name of the file in the hash if it was provided.
                    // The attachzone parser may ignore this
                    inputValue = data.hash;
                    if (data.meta.name) {
                        inputValue = inputValue + ':' + String(data.meta.name);
                    }

                    $(file.previewElement)
                        .find('input[data-az-attachment]')
                        .val(inputValue);
                    self.attach(data);

                    azone.trigger('success.attachzone', [data]);

                });

                this.on('error', function (file, errMsg, xhr) {
                    self.rejectFile(file, errMsg, this);
                    azone.trigger('error.attachzone', [file, errMsg]);
                });

                // Disable form submission while uploading a file
                var submit = dzone.closest('form').find(':submit');
                this.on('sending', function () {
                    submit.prop('disabled', true);
                });
                // Re-enable form submission when upload is done
                this.on('queuecomplete', function () {
                    submit.prop('disabled', false);
                });
            },

            removedfile: function (_file) {
                self.detach(
                    _file.previewElement,
                    function () {
                        azone.trigger('removedfile.attachzone', [_file]);
                    }
                );
                //manually ensure the file has been removed, as it seems files
                //added via the modal aren't notifying DropZone properly even though
                //the 'removedfile' event is still called
                if (_file.status !== 'error') {
                    var dropZone = this;
                    dropZone.files.forEach(function (file, index) {
                        if (file.hash === _file.hash) {
                            dropZone.files.splice(index, 1);
                        }
                    });
                    dropZone._updateMaxFilesReachedClass();
                }
            },

            maxfilesexceeded: function (file) {
                this.removeFile(file);
                azone.trigger('maxfilesexceeded.attachzone', [file]);
            },

            accept: function (file, done) {

                // no hash yet: treat as normal dz upload
                if (file.hash === undefined) {
                    done();
                    azone.trigger('accept.attachzone', [file]);
                    return;
                }

                // has hash: this means we've already got its storage reference
                self.renderFile(file);

                // trick dropzone into treating this file as though it were successfully uploaded
                file.accepted = true;
                file.status = Dropzone.SUCCESS;

                this._updateMaxFilesReachedClass();
                azone.trigger('accept.attachzone', [file]);

            }
        });


        // check for existing attachments and push them into the dropzone's list
        azone.find(opt.previewsContainerSelector).find(opt.attachmentTag).each(function () {
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
        } else {
            this.fallbackMaxFilesReached();
        }

        // cache the content for this reveal modal
        this.cacheModal();

        if (opt.useModal) {
            $(this.element).on('click', opt.trigger, function (e) {
                e.preventDefault();
                self.open();
            });
        }

    }


    /**
     *
     * @param {File|object}   file
     * @param {string|object} errMsg
     */
    ResourceAttach.prototype.rejectFile = function (file, errMsg) {
        var data, element, t,
            errList = $(this.element).find('.az-error-list');

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

        element.find('.dz-progress, input').detach();
        t = element.find('[data-dz-name]').text();
        element.find('[data-dz-name]').text(t + ' failed: ' + data).attr('title', data);

        // Replace bin icon with x
        errList.find('nav .icon-delete').removeClass('icon-delete').addClass('icon-close');

        if (this.dropzone) {
            this.dropzone.removeFile(file);
        }
    };


    ResourceAttach.prototype.getQueryString = function () {
        var qString = 'multi=' + (this.options.multi ? '1' : '0');

        if (this.options.allowedFiles) {
            qString += '&mime=' + this.options.allowedFiles;
        }

        if (this.options.useFolder) {
            qString += '&f=1';
        }

        if (this.options.fs) {
            qString += '&fs=' + this.options.fs;
        }

        return qString;
    };


    ResourceAttach.prototype.getCacheKey = function () {
        return this.options.source + '?' + this.getQueryString();
    };


    ResourceAttach.prototype.cacheModal = function (callback) {

        var key = this.getCacheKey(),
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

        this.attachmentBrowserFilesystemsInnerHTML = $(data).find('#attachment-browser-filesystems')[0].innerHTML;
        this.attachmentBrowserPanelContainerInnerHTML = $(data).find('#attachment-browser-panel-container')[0].innerHTML;

        if (singletonModal) {
            // you've got a modal; don't try to make another one
            return;
        }

        // if the modal already exists, destroy it
        this.destroy();

        singletonModal = $('<div data-reveal class="reveal-modal file-picker" id="file-picker" />')
            .data('content', data)
            .data('rtn', this);

        // reinit
        $(document)
            //.foundation('reveal', 'reflow')
            .on('closed.fndtn.reveal', '[data-reveal]', function () {
                if (singletonModal) {
                    singletonModal.detach();
                    singletonModal = null;
                }
            });


        singletonModal.on('click', '.cancel[role=button]', function (e) {
            window.top.postMessage(JSON.stringify({data: []}), location.origin);
        });
    };


    ResourceAttach.prototype.open = function () {

        $('body').append(singletonModal); //add to the DOM

        var self = this;

        if (singletonModal == null) {
            // this will construct a modal VIA the cache
            this.cacheModal(function () {
                singletonModal
                    .html(singletonModal.data('content'))
                    .foundation('reveal', 'open')
                    .off('closed.fndtn.reveal.attach')
                    .on('closed.fndtn.reveal.attach', self.reopenParentReveal.bind(self));
            });
        } else {
            // ensure the return points to the right element
            singletonModal
                .data('rtn', this)
                .html(singletonModal.data('content'))
                .foundation('reveal', 'open')
                .off('closed.fndtn.reveal.attach')
                .on('closed.fndtn.reveal.attach', self.reopenParentReveal.bind(self));
        }

        this.initAttachmentBrowser();

        // for iframe communication

        $(window).unbind('message').on('message', function (e) {
            var message,
                modal = singletonModal,
                self = modal.data('rtn'),
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
                $(elems).each(function () {
                    this.addClass('dz-success')
                });

                // Trigger attachzone success event

                $(self.element).trigger('success.attachzone', [message]);
            }
        });
    };


    /**
     * There can be several DropZone instances on a page, one could allow multi uploads, another just single.
     * Due to the use of singletonModal, this is an issue. Refer to SA-1464 for further details.
     * Essentially though, both the relevant menu ('#attachment-browser-filesystems') and panel ('#attachment-browser-panel-container')
     * are injected into the modal window when the Modal Window is clicked.
     * This ensures that the appropriate upload count / mime types are respected
     */
    ResourceAttach.prototype.initAttachmentBrowser = function () {
        singletonModal.find('#attachment-browser-filesystems')[0].innerHTML = this.attachmentBrowserFilesystemsInnerHTML;
        singletonModal.find('#attachment-browser-panel-container')[0].innerHTML = this.attachmentBrowserPanelContainerInnerHTML;
    };


    /**
     * Is this attachzone in a reveal itself? Reopen it if so.
     *
     * @param event
     */
    ResourceAttach.prototype.reopenParentReveal = function (event) {
        // compensate for event double-fire: see https://github.com/zurb/foundation/issues/5482
        if (event) {
            if (event.namespace !== 'fndtn.reveal' || $('[data-reveal].open').length > 0) {
                return;
            }
            if (event.type === 'closed') { //close down the recorders and reset for possible re-display
                document.dispatchEvent(new CustomEvent('record-media-modal__onModalClosed'));
            }
        }
        var parentReveal = $(this.element).closest('[data-reveal]');
        if (parentReveal.length) {
            parentReveal.foundation('reveal', 'open');
        }
        singletonModal.detach(); //remove from the DOM, is added again on .open()
    };


    ResourceAttach.prototype.close = function () {
        if (singletonModal && singletonModal.is(':visible')) {
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

            if (this.dropzone) { //from the Upload files modal option

                if (list[i].status === 'error') { //otherwise dummy up the error display that occurs when using straight drag n drop without the modal

                    let fileName = list[i].name;
                    let errorMessage = list[i].errorMessage;
                    let failedFileItem = document.createRange().createContextualFragment('<li class="actions-small-1 dz-error"><div class="list-item"><div class="card small-12"><p class="filename" data-dz-name="" title="' + errorMessage + '">' + fileName + ' failed: ' + errorMessage + '</p></div></div><nav><a href="#" data-dz-remove="" title="Remove" class="icon-close"></a></nav></li>');
                    this.element.querySelector('.az-error-list').appendChild(failedFileItem);

                } else { //only display those files that were succesfully uploaded from the modal window

                    //if the file was attached using 'Upload file(s)' it's been uploaded,
                    //so we can display the image in the drop zone when we return to the page
                    let getExistingFileUrl = function () {
                        if (list[i].file && list[i].file.imageUrl) { //this file prop is set within templates/core/resources/filesystem/upload.html - onQueueComplete()
                            return list[i].file.imageUrl;
                        } else { //otherwise we'll just show the file type image
                            return '/ajax/mime/' + data.type;
                        }
                    }

                    //once the files displayed, ensure the drop zone window and progress view are hidden depending on max uploads
                    let onExistingFileDisplayed = function () {
                        let elementDOM = $(self.element)[0];
                        if (elementDOM.id === 'cover-photo-zone') {
                            elementDOM.querySelector('.az-list').style.display = 'none';
                        } else {
                            if (self.dropzone.options.maxFiles === 1) {
                                elementDOM.classList.add('dz-max-files-reached');
                            }
                        }
                        if (elementDOM.querySelector('.progress')) {
                            elementDOM.querySelector('.progress').style.display = 'none';
                        }

                    }

                    //to display an existing file within the drop zone view, as the file has already been loaded
                    //https://gitlab.com/meno/dropzone/-/wikis/FAQ#how-to-show-files-already-stored-on-server
                    this.dropzone.displayExistingFile(data, getExistingFileUrl(), onExistingFileDisplayed, 'Anonymous');

                    //as this.on('success') is not called when using the modal window upload,
                    //need to also add the attachment vars in so the files are added on form submit
                    let inputValue = data.hash + ':' + data.name;
                    $(data.previewElement)
                        .find('input[data-az-attachment]')
                        .val(inputValue);
                    self.attach(data);

                    elems.push($(data.previewElement));

                    $(this.element).trigger('success.attachzone', [data]);

                    list[i].accepted = true; //ensure any dropzone.getAcceptedFiles() checks are valid
                    this.dropzone.files.push(list[i]);

                    // now the file has been added...if it's buggy, take it out again
                    if (data.hash.split(':')[0] == 'exception') {
                        this.rejectFile(data, data);
                    }

                }

            } else { //all others excluding Upload files option

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

                // now the file has been added...if it's buggy, take it out again
                if (data.hash.split(':')[0] == 'exception') {
                    this.rejectFile(data, data);
                }

            }

        }

        return elems;

    };


    ResourceAttach.prototype.detach = function (elem, then) {
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
            if ($.isFunction(then)) {
                then();
            }
        }, this.options.detachDelay);
    };


    ResourceAttach.prototype.renderFile = function (file) {

        var elem;

        if (!file.previewElement) {
            return;
        }

        elem = $(file.previewElement);

        // render whatever variables are available
        if (file.name) {
            elem.find('[data-dz-name]').html(file.name);
        }
        if (file.hash) {
            var hash = file.hash;
            // If there's a file name: append it to the hash
            // (thus completing SA-10735)
            if (file.name) {
                hash = hash + ':' + file.name;
            }
            elem.find('input[data-az-attachment]').val(hash);
        }

        if (file.type) {
            elem.find('img[data-dz-thumbnail]').attr('src', '/ajax/mime/' + file.type);
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
