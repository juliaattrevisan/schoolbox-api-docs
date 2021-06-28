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
 *
 * Note that the Filesystem options, aliased via 'allowedFilesystems' parameter, are found in the
 * Resource\Attachment\Context\Factory so if you need to adjust the available options, do it there
 */
(function() {
    var modalCache = {};
    var singletonModal = null;


    /**
     * Attempt to restore the previous modal with the identifier $id
     * Reopens its ui and reanimates its overlay
     *
     * @param id
     */
    $.fn.schoolboxRestorePreviousModal = function(id)
    {
        if (null === id || undefined === id) {
            return;
        }

        /* Select it, in case it was provided as just an id string
         */
        $(id).foundation('reveal', 'open');

        /* M3 picker shut this overlay so I have to manually restore it after
         * foundation finishes its animation
         */
        setTimeout(function() {
            $('.reveal-modal-bg')
                .css('opacity', 0)
                .css('display', 'block')
                .animate({
                    opacity: 1
                }, 250);
        }, 275);
    }


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

                /* Url to get node information
                 */
                source: '/resources/attachment',

                /* Comma-separated list of extensions to filter
                 */
                allowedFiles: null,

                /* Allowed Filesystems for choosing resources
                 */
                allowedFilesystems: false,

                /* Whether Use This Folder is available
                 */
                useFolder: true,

                /* Whether the singleton modal should allow detachment
                 *
                 * Set to false when the picker is spawned from within some other modal
                 * because that modal will shut, causing a reinit and this singleton detachment.
                 *
                 * That causes your file picker modal to inexplicably vanish, as it destroys itself
                 * when another modal shuts. It's pretty weird. The original idea was that the singletonModal
                 * could only exist once, and in a modal; any time it shut, it must reinit - but the
                 * selector applies to all modals, not just the picker one. Maybe that's the next change.
                 */
                allowDetachment: true,

                /* If specified, try to restore the modal designated by this identifier
                 * You only need to do this if the M3 picker starts from within another modal
                 * There were only two places at the time of writing: My Links and Socialstream
                 */
                restoreModal: null,

                /* Selector to get the default folder to start browsing resources from
                 */
                defaultFolderSelector: 0,

                /* Executes before picker modal opens
                 */
                onBeforeOpen: function() {
                },

                /* Executes after picker modal shuts
                 */
                onAfterClose: function() {
                }
            };

            return $.extend(defaults, options);
        }


        /**
         * Augment url with parameters
         *
         * @returns {string}
         */
        function getQueryString() {
            var qString = 'multi=0';

            /* Didn't specify a mimetype for files; display folders and all files via the empty string
             * Or, if you specified an allowedFiles string, use that as a filter
             */
            if (false === config.allowedFiles) {

                /* Only show folders (disallow all files)
                 * Specify an empty mime querystring param
                 */
                qString += '&mime=';
            } else if (null === config.allowedFiles) {

                /* Show both folders and any/all files
                 * This is the default behaviour (noop)
                 */

            } else {

                /* Filter to specific filetypes
                 */
                qString += '&mime=' + config.allowedFiles;
            }

            /* Pick from the presets as to what controllers are available
             */
            if (config.allowedFilesystems) {
                qString += '&fs=' + config.allowedFilesystems;
            }

            /* If the Use This Folder button is available
             */
            if (config.useFolder) {
                qString += '&f';
            }

            /* If a default Folder is specified, just go straight to that node to
             * save users the immense hassle of navigating the folder tree
             */
            if (config.defaultFolderSelector) {
                qString += '&fid=' + config.defaultFolderSelector;
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

            /* null !== config.restoreModal was previously known as allowDetachment
             *
             * Whether the singleton modal should allow detachment
             * (meaning, allow modal to destroy itself when any modal shuts)
             *
             * Set this when the picker is spawned from within some other modal
             * because that modal will shut, causing a reinit and this singleton detachment.
             *
             * That causes your file picker modal to inexplicably vanish, as it destroys itself
             * when another modal shuts. It's pretty weird. The original idea was that the singletonModal
             * could only exist once, and in a modal; any time it shut, it must reinit - but the
             * selector applies to all modals, not just the picker one. Maybe that's the next change.
             */
            if (singletonModal && (null === config.restoreModal || undefined === config.restoreModal)) {

                /* Forcibly reopen it anyway. You can enter this code path by opening a socialstream reply
                 * M3 picker, then cancel it, then try to open a socialstream post M3 where
                 * restoreModal is empty and singletonModal existed, but was shut (so reopen it)
                 */
                singletonModal
                    .html(singletonModal.data('content'))
                    .foundation('reveal', 'open')
                    .off('closed.fndtn.reveal.attach');

                return;
            } else if($('#attachment-browser').length) {
                singletonModal = $('#attachment-browser').parent();
            } else {
                singletonModal = $('<div data-reveal class="reveal-modal file-picker" id="file-picker" />')
                    .data('content', data);

                body.append(singletonModal);
            }

            config.onBeforeOpen();

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
                    if (singletonModal && (null === config.restoreModal || undefined === config.restoreModal)) {
                        singletonModal.detach();
                        singletonModal = null;
                    }

                    if (undefined !== config.previousModal) {
                        config.previousModal.removeClass('hide');
                    }

                    // Show the cke dialog overlay again
                    $('.cke_dialog_background_cover').removeClass('hide');
                });

            singletonModal.on('click', '.cancel[role=button]', function (e) {
                e.preventDefault();
                window.top.postMessage(JSON.stringify({ data: [] }), location.origin);
                singletonModal.foundation('reveal', 'close');

                config.onAfterClose();

                $.fn.schoolboxRestorePreviousModal(config.restoreModal);
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
         *
         * Depending on what you chose, the url is constructed a bit specially
         * sorry
         *
         * @param type        string  data type [folder, myfolder, file, meetingUrl]
         * @param filesystem  string  typically 'store' as a special case usage
         * @param hash        string  a hashed string for a storage object
         * @param val         string  a file id, myFile id, or a meeting url
         * @return url        string  the url to which you are linking
         */
        function processSelection(type, filesystem, hash, val) {
            var url;

            /* The args to this method come from an async request to nodeinfo
             * Urls can be generated contextually based on some combination of these args
             * Here, I've consolidated known outcomes as best I could
             */
            if (type === 'folder') {

                /* When you select Use This Folder which is available in special circumstances
                 */
                url = config.url.folder + val;
            } else if (filesystem === 'store') {

                /* When you Upload A File via the modal's dropzone
                 */
                url = config.url.storage + hash;
            } else if (filesystem === 'browse') {

                /* When you select a file via Resource Browser
                 */
                url = config.url.storage + hash;
            } else if (filesystem === 'office365') {

                /* When you select a file via Office365
                 */
                url = config.url.storage + hash;
            } else if (filesystem === 'myfile') {

                /* When you select a My File
                 */
                url = config.url.storage + hash;
            } else if (filesystem === 'webdav') {

                /* When you select a WebDAV file
                 */
                url = config.url.webdav + val;
            } else if (type === 'meetingUrl') {

                /* When you select a Microsoft Teams meeting url
                 */
                url = config.url.meetingUrl + val;
            } else {

                /* Default assumption (not sure where this manifests anymore, sorry)
                 */
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
                    var status = 'success';
                    if ('status' in message.data[0]) {
                        status = message.data[0].status;
                    }
                    if (status === 'success') {
                        $.getJSON(config.url.nodeinfo, {identifier: message.data[0].hash}, function (responseText) {
                            config.onSelect(
                                processSelection(responseText.type, responseText.filesystem, responseText.hash, responseText.id),
                                responseText.name,
                                responseText.mime,
                                dialog,
                                responseText
                            );

                            /* Shut modal, go back to Dialog
                             */
                            $('#file-picker').foundation('reveal', 'close');

                            config.onAfterClose();

                            $.fn.schoolboxRestorePreviousModal(config.restoreModal);
                        });
                    } else {
                        // Unknown status: assume error and do nothing, as the modal already shows the error
                    }
                } else if ('meetingUrl' in message.data) {

                    /* Microsoft Teams meetings url format
                     */
                    config.onSelect(processSelection('meetingUrl', '', '', message.data.meetingUrl), 'Microsoft Teams Meeting', null, dialog);

                    /* Shut modal, go back to Dialog
                     */
                    $('#file-picker').foundation('reveal', 'close');

                    config.onAfterClose();

                    $.fn.schoolboxRestorePreviousModal(config.restoreModal);
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

                /* Prefix for linking to MyFiles
                 */
                myfile: '/storage/fetch.php?hash=',

                /* Prefix for link to Storage hash
                 */
                storage: '/storage/fetch.php?hash=',

                /* Prefix for link to WebDAV
                 */
                webdav: '/cms/sendNetworkFile.php?id=',

                /* Prefix for linking to Files
                 */
                file: '/send.php?id=',

                /* Prefix for Microsoft Teams meeting urls (normally empty, don't need to touch it)
                 */
                meetingUrl: '',

                /* restoreModal specifies whether there's a modal that should be restored once the M3 shuts
                 * This is in the form of an id string for the modal but you can use a jquery selector too
                 *
                 */
                restoreModal: null
            },

            /**
             * A callback which you use to deal with the url produced
             * by interacting with the modal
             *
             * @param url      string the url to which you linked
             * @param name     string user-facing name of the thing you linked
             * @param mime     string the mime type of the thing you linked
             * @param dialog   object the cke dialog to update (supply null if you aren't using cke dialogs)
             * @param response object the actual JSON response for the thing you linked
             */
            onSelect: function(url, name, mime, dialog, response) {
                return url;
            },

            /**
             * Executes after the M3 picker shuts its own modal
             */
            onAfterClose: function() {
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

        var config = $.extend(

            /* Default filesystem is 'link' for this usage
             */
            {
                element: $(this),
                allowedFiles: null,
                allowedFilesystems: 'link',
                defaultFolderSelector: null
            },

            /* Combine with modal configuration requirements
             */
            $.fn.schoolboxModalConfig(options)

            /* Other elements of this object literal as a result of merging with defaults:
             *
             * url: (string)
             * onBeforeOpen: function() ...
             * onSelect: function() ...
             * onAfterClose: function() ...
             * restoreModal: (string)
             */
        );

        /* When $(element) is clicked, spawn modal and process selection
         * as per the callback you optionally specified via constructor
         */
        $(config.element).on('click', function() {

            /* Select something from the Resource Browser
             */
            $.fn.schoolboxResource({
                allowedFiles: config.allowedFiles,
                allowedFilesystems: config.allowedFilesystems,
                allowDetachment: config.allowDetachment,
                defaultFolderSelector: config.defaultFolderSelector,
                onBeforeOpen: config.onBeforeOpen,
                onAfterClose: config.onAfterClose,
                restoreModal: config.restoreModal,
            });

            /* Send the message up and deal with the dialog, if one was specified
             */
            $.fn.schoolboxMessage(config, null);
        });
    }
}(jQuery));
