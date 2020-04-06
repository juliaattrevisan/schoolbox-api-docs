/**
 * Mr Matt's Modal
 *
 * This lets you pick a folder, upload a file, provide a recording, choose something from google drive,
 * make a Microsoft Teams Meeting url, etc. and get that as a link
 *
 * This is a prerequisite for our ckeditor implementation which uses custom plugins that need this modal
 *
 * Assembly requires multiple individual plugins:
 *
 * schoolboxResource - pick a resource while you're in the cke dialog context
 * schoolboxMessage - the thing you chose is sent via postMessage() so the cke dialog can get it
 * schoolboxModalConfig - default configuration of the modal so it can interpret urls that you chose
 * schoolboxLink - the plugin you can use to spawn the modal, pick a link, and put it somewhere in th dom
 */
(function() {
    var modalCache = {};
    var singletonModal = null;

    /**
     * Spawns a Schoolbox Resource Browser which allows you
     * to choose a file of some sort
     *
     * Shamelessly cannibalised from Dropzone Resource Browser
     *
     * @param options
     */
    $.fn.schoolboxResource = function(options) {
        options = options || {};

        var config = init(options, $(this));

        cacheModal();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                element: element,

                /* Comma-separated list of extensions to filter
                 */
                allowedFiles: null,

                /* Url to get node information
                 */
                source: '/resources/attachment',

                /* Allowed Filesystems for choosing resources
                 */
                allowedFilesystems: false
            };

            var derived = {
            };

            return $.extend(defaults, derived, options);
        }

        /**
         * Augment url with parameters
         *
         * @returns {string}
         */
        function getQueryString() {
            var qString = 'multi=0';

            if (config.allowedFiles) {
                qString += '&mime=' + config.allowedFiles;
            }

            if (config.allowedFilesystems) {
                qString += '&fs=' + config.allowedFilesystems;
            }

            return qString;
        }

        /**
         * Unique identifier for modals
         *
         * @returns {string}
         */
        function getCacheKey() {
            return config.source + '?' + getQueryString();
        }

        /**
         * Respawn a modal from the cache if it already existed for
         * a given url
         */
        function cacheModal() {
            var key = getCacheKey();

            if ('undefined' == typeof modalCache[key]) {
                modalCache[key] = $.get(config.source, getQueryString()).promise();
            }

            modalCache[key].done(function(data) {
                // ensure we retain ResourceAttache context
                build(data);
            });
        }

        /**
         * Resize picker dimensions dynamically
         */
        function resizePicker() {
            var pickerWidth = $(window).width() * 0.67;

            $('.reveal-modal.file-picker.open').css({
                width: pickerWidth + 'px',
                left: Math.floor($(window).width()/2 - pickerWidth/2) + 'px'
            });
        }

        /**
         * Manufacture a modal
         *
         * @param data
         */
        function build(data) {
            var body = $('body');

            if (singletonModal) {
                // you've got a modal; don't try to make another one
                return;
            }
            else if($('#attachment-browser').length) {
                singletonModal = $('#attachment-browser').parent();
            }
            else {
                singletonModal = $('<div data-reveal class="reveal-modal file-picker" id="file-picker" />')
                    .data('content', data);

                body.append(singletonModal);
            }

            singletonModal
                .html(singletonModal.data('content'))
                .foundation('reveal', 'open')
                .off('closed.fndtn.reveal.attach');

            /* If you're opening the modal in cke_maximised,
             * you have to wrangle styles around so it doesn't
             * appear in a stupid layout
             *
             * For some reason cke fights foundation and kills responsiveness
             * so dynamically assign a pixel width upon initiation and
             * also aupon resization
             */
            if ($('.cke_maximized').length) {
                $(window).resize(function() {
                    resizePicker();
                });

                $(document).on('opened.fndtn.reveal', '.file-picker', function() {
                    resizePicker();
                });
            }

            /* Hide cke overlay and adjust a z-index so the
             * Schoolbox Resource Browser appears on top of the
             * ckeditor dialog (enormous sigh)
             */
            $('.cke_dialog_background_cover').addClass('hide');

            /* ckeditor dialog appears at z-index 10010 so our
             * Resource Browser needs to be more
             */
            $('[data-reveal]').css({zIndex: 20000});

            // reinit
            $(document)
                //.foundation('reveal', 'reflow')
                .on('closed.fndtn.reveal', '[data-reveal]', function () {
                    if (singletonModal) {
                        singletonModal.detach();
                        singletonModal = null;
                    }
                    if(undefined !== config.previousModal) {
                        config.previousModal.removeClass('hide');
                    }

                    // Show the cke dialog overlay again
                    $('.cke_dialog_background_cover').removeClass('hide');
                });

            singletonModal.on('click', '.cancel[role=button]', function (e) {
                e.preventDefault();
                window.top.postMessage(JSON.stringify({ data: [] }), location.origin);
                singletonModal.foundation('reveal', 'close');
            });
        }
    };


    /**
     * Pass whatever you chose in the modal to the window messaging thing
     *
     * @param config  object  a configuration object literal
     * @param dialog  object  the cke dialog to update (use null if you aren't in a cke context)
     */
    $.fn.schoolboxMessage = function(config, dialog) {

        /**
         * Construct a url from the pieces provided
         * Depending on what you chose, the url is constructed a bit specially
         * sorry
         *
         * @param type        string  data type [folder, myfolder, file, meetingUrl]
         * @param filesystem  string  typically 'store' as a special case usage
         * @param val         string  a file id or a meeting url
         * @return url        string  the url to which you are linking
         */
        function processSelection(type, filesystem, val) {
            var url;

            if (type == 'folder') {
                url = config.url.folder + val;
            } else if (type == 'myfolder') {
                url = config.url.myfolder + val;
            } else if (filesystem == 'store') {
                url = config.url.storage + val;
            } else if (type =='meetingUrl') {
                url = config.url.meetingUrl + val;
            } else {
                url = config.url.file + val;
            }

            return url;
        }

        $(window).unbind('message').on('message', function(event) {
            var message;

            try {
                message = JSON.parse(event.originalEvent.data);
            } catch (err) {
                message = event.originalEvent.data;
            }

            /* Find out how to render this file
             */
            if (message.data != undefined) {
                if (0 in message.data && 'hash' in message.data[0]) {
                    $.getJSON(config.url.nodeinfo, {identifier: message.data[0].hash}, function (responseText) {
                        console.log(responseText);
                        config.onSelect(processSelection(responseText.type, responseText.filesystem, responseText.id),dialog);

                        /* Shut modal, go back to Dialog
                         */
                        $('#file-picker').foundation('reveal', 'close');
                    });
                } else if ('meetingUrl' in message.data) {

                    /* Microsoft Teams meetings url format
                     */
                    config.onSelect(processSelection('meetingUrl', '', message.data.meetingUrl), dialog);

                    /* Shut modal, go back to Dialog
                     */
                    $('#file-picker').foundation('reveal', 'close');
                } else {

                    // The file was rejected, probably cause it was the wrong mimetype.
                    // Do nothing
                }
            }
            else {

                /* Connecting to your Google Drive
                 */
            }
        });
    };


    /**
     * Spawn a modal config object literal with auxiliary options such
     * as the onSelect callback
     *
     * @param options
     * @returns {*}
     */
    $.fn.schoolboxModalConfig = function(options) {
        return $.extend({
            url: {
                /* Find information about a node/resource
                 */
                nodeinfo: '/editor/nodeInfo.php',

                /* Prefix for linking to Folders
                 */
                folder: '/homepage/',

                /* Prefix for linking to MyFolders
                 */
                myfolder: '/my-files/',

                /* Prefix for link to Storage hash
                 */
                storage: '/storage/fetch.php?hash=',

                /* Prefix for linking to Files
                 */
                file: '/send.php?id=',

                /* Prefix for Microsoft Teams meeting urls (normally empty, don't need to touch it)
                 */
                meetingUrl: ''
            },

            /**
             * A callback which you use to deal with the url produced
             * by interacting with the modal
             *
             * @param url     string  the url to which you linked
             * @param dialog  object  the cke dialog to update (supply null if you aren't using cke dialogs)
             */
            onSelect: function(url, dialog) {
                return url;
            }
        }, options);
    };


    /**
     * Plugin
     *
     * The schoolboxLink plugin assembles the picker modal out of the the required parts
     * schoolboxResource, schoolboxMessage, schoolboxMessageConfig and returns you a url
     * for the thing you chose or provided via that modal
     *
     * Using the onSelect() callback allows you to deal with the url in some special way
     *
     * @param options  object
     */
    $.fn.schoolboxLink = function(options) {
        options = options || {};

        var config = $.extend($.fn.schoolboxModalConfig(options), {
            element: $(this),
            allowedFilesystems: 'link',

            /* Other elements of this object literal as a result of merging with defaults:
             *
             * url: ...
             * onSelect: function() ...
             */
        });

        /* When $(element) is clicked, spawn modal and process selection
         * as per the callback you optionally specified via constructor
         */
        $(config.element).on('click', function() {

            /* Select something from the Resource Browser
             */
            $.fn.schoolboxResource({
                allowedFilesystems: config.allowedFilesystems
            });

            /* Send the message up and deal with the dialog, if one was specified
             */
            $.fn.schoolboxMessage(config, null);
        });
    }
}(jQuery));
