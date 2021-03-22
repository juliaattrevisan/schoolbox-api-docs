CKEDITOR.dialog.add('schoolboxresourceDialog', function (editorInstance) {

    var config = {
        url: {
            /* Find information about a node/resource
             */
            nodeinfo: '/editor/nodeInfo.php'
        },

        /* Video urls and regex
         */
        video: {
            source: function(id, filesystem) {
                return '/cms/sendVideo.php?id=' + id + '&type=' + filesystem;
            },
            thumbnail: function(id, filesystem) {

                /* Ridiculous quick fix
                 */
                filesystem = filesystem.replace('browse', 'sbfs').replace('store', 'storage');

                return '/cms/videoThumb.php?size=poster&id=' + id + '&type=' + filesystem;
            },
            regex: /^\/cms\/sendVideo/
        },

        /* Audio urls and regex
         */
        audio: {
            source: function(id, filesystem) {
                return '/cms/sendVideo.php?id=' + id + '&type=' + filesystem;
            },
            thumbnail: '/static/images/core/component/audio-thumbnail-processing.gif',
            regex: /^\/cms\/sendVideo/
        },

        /* Image urls and regex
         * No thumbnails are used for images
         */
        image: {
            source: function(hash) {
                return '/storage/image.php?hash=' + hash;
            },
            regex: /^\/storage\/image/
        },

        /* ID of the default folder from which to start browsing resources
         */
        defaultFolderSelector: 0
    };

    /**
     * Populate dialog fields with image data
     *
     * @param fields
     * @param url
     * @param reset
     */
    function render(fields, url, reset) {

        /* Apply width/height dimensions upon thumbnail
         * forcing it to have dimensions (for mejs)
         */
        var enforceDimensions = false;

        /* Special case for video url
         */
        if (fields.type.getValue() == 'video') {
            url = config.video.thumbnail(fields.id.getValue(), fields.filesystem.getValue());
            enforceDimensions = true;
        }
        else if (fields.type.getValue() == 'audio') {
            url = config.audio.thumbnail;
            enforceDimensions = true;
        }

        /* Find out width/height ratio
         */
        var temp = new Image();
        temp.src = url;
        temp.onload = applyDimensions;

        /* Apply dimensions upon temp image spawn
         */
        function applyDimensions() {
            var width = this.width;
            var height = this.height;
            var preview = '<img src="'+url+'" style="width:100%;" />';

            /* Set dialog input contents
             */
            $('.' + fields.parentSelector).find('#schoolboxresource-preview').contents().find('html').html(preview);

            /* Don't change width or height if they were already set
             *
             * This occurs when setup() runs, when editing an existing image
             * Or when an image was set to Fullwidth (it won't have a width/height style attribute)
             *
             * Force video/audio elements to have width/height dimensions, for mejs player
             */
            if (reset || enforceDimensions) {
                fields.width.setValue(width);
                fields.height.setValue(height);
            }

            /* Only allow using Constrain Aspect Ratio if a width
             * and a height has been provided
             */
            if (fields.width.getValue() && fields.height.getValue()) {
                fields.aspectRatio.enable();
                fields.aspectRatio.setValue(true);
            }
            else {
                fields.aspectRatio.disable();
                fields.aspectRatio.setValue(false);
            }

            /* Reset aspect ratio value
             */
            aspectRatioReset(fields);
        }
    }

    /**
     * Reset the aspect ratio for an image
     *
     * Used when selecting a new image with constrain aspect ratio already activated
     * because that remembers the aspect ratio from the previously-selected image
     *
     * @param fields
     */
    function aspectRatioReset(fields) {

        /* Only do something useful if the width and height are both set
         * Otherwise results are not sane
         */
        if (fields.width.getValue() && fields.height.getValue()) {
            fields.aspectRatioValue.setValue(fields.width.getValue() / fields.height.getValue());
        }
    }

    /**
     * Inject image into editor panel with modifiers applied
     *
     * @param editorInstance
     * @param fields
     * @param url
     * @returns {*}
     */
     function inject(editorInstance, fields, url) {
        var dataVars = {};

        /* Special case for video data
         *
         * data-schoolboxresource-width and -height correspond to the initial state
         * of the video
         */
        if (fields.type.getValue() == 'video') {
            url = config.video.thumbnail(fields.id.getValue(), fields.filesystem.getValue());

            dataVars = {
                'data-schoolboxresource-type': 'video',
                'data-schoolboxresource-filesystem': fields.filesystem.getValue(),
                'data-schoolboxresource-thumbnail': url,
                'data-schoolboxresource-source': config.video.source(fields.id.getValue(), fields.filesystem.getValue()),
                'data-schoolboxresource-width': fields.width.getValue(),
                'data-schoolboxresource-height': fields.height.getValue(),

                /* Faux percentage setting used in mejs
                 */
                'data-schoolboxresource-fullwidth': fields.fullwidth.getValue()
            };
        }
        /* Special case for audio data
         */
        else if (fields.type.getValue() == 'audio') {
            url = config.audio.thumbnail;

            dataVars = {
                'data-schoolboxresource-type': 'audio',
                'data-schoolboxresource-filesystem': fields.filesystem.getValue(),
                'data-schoolboxresource-thumbnail': url,
                'data-schoolboxresource-source': config.audio.source(fields.id.getValue(), fields.filesystem.getValue()),
                'data-schoolboxresource-width': fields.width.getValue(),
                'data-schoolboxresource-height': fields.height.getValue()
            };
        }
        /* Images render with provided information
         */
        else if (fields.type.getValue() == 'image') {
            dataVars = {
                'data-schoolboxresource-type': 'image',
                'data-schoolboxresource-filesystem': fields.filesystem.getValue(),
                'data-schoolboxresource-thumbnail': url,
                'data-schoolboxresource-source': url,
                'data-schoolboxresource-width': fields.width.getValue(),
                'data-schoolboxresource-height': fields.height.getValue()
            };
        }

        /* Render image
         */
        var styles = [];
        var classes = [];

        var element = editorInstance.document.createElement('img');
        element.setAttribute('src', url);
        element.setAttributes(dataVars);

        /* Determine width - if fullwidth checked, use 100%
         * otherwise use specified values. Note that width:100%
         * is not allowed, so use the usual class instead
         *
         * Include both styles and classes to display this; because
         * of htmlpurifier and ckeditor iframe respectively
         */
        if (fields.fullwidth.getValue()) {
            classes.push('full-width');
            styles.push('width:100%');
        }
        else {
            if (fields.width.getValue()) {
                styles.push('width:' + fields.width.getValue() + 'px');
            }

            if (fields.height.getValue()) {
                styles.push('height:' + fields.height.getValue() + 'px');
            }
        }

        /* Alignment
         * Floats left and right;
         * centered uses 0 auto;
         * none is no floating, just display inline
         */
        if (fields.alignment != undefined) {
            switch (fields.alignment.getValue()) {

                /* Center aligned, no text wrapping
                 */
                case 'center':
                    styles.push('margin:0 auto');
                    styles.push('display:block');
                    break;

                /* Left aligned, text wraps against right edge
                 */
                case 'left':
                    styles.push('float:' + fields.alignment.getValue());

                    /* Horizontal Spacing (applies margin-left and margin-right)
                     * Ignore margin-left when left aligned
                     */
                    if (fields.horizontalMargin.getValue() != '') {
                        styles.push('margin-right:' + fields.horizontalMargin.getValue() + 'px');
                    }

                    break;

                /* Right aligned, text wraps against left edge
                 */
                case 'right':
                    styles.push('float:' + fields.alignment.getValue());

                    /* Horizontal Spacing (applies margin-left and margin-right)
                     * Ignore margin-right when right aligned
                     */
                    if (fields.horizontalMargin.getValue() != '') {
                        styles.push('margin-left:' + fields.horizontalMargin.getValue() + 'px');
                    }

                    break;

                /* Left aligned, no text wrapping
                 */
                default:

                    /* Horizontal Spacing (applies margin-left and margin-right)
                     */
                    if (fields.horizontalMargin.getValue() != '') {
                        styles.push('margin-left:' + fields.horizontalMargin.getValue() + 'px');
                        styles.push('margin-right:' + fields.horizontalMargin.getValue() + 'px');
                    }

                    break;
            }
        }

        /* Vertical Spacing (applies margin-top and margin-bottom)
         */
        if (fields.verticalMargin.getValue() != '') {
            styles.push('margin-top:' + fields.verticalMargin.getValue() + 'px');
            styles.push('margin-bottom:' + fields.verticalMargin.getValue() + 'px');
        }

        /* Border
         */
        if (fields.border.getValue() != '') {
            styles.push('border:1px solid #808080;');
        }

        /* Combine and inject
         */
        element.setAttribute('class', classes.join(' '));
        element.setAttribute('style', styles.join(';'));

        return element;
    }

    /**
     * A macro to all the fields in the Dialog
     * (because it's such a longwinded way to retrieve them)
     *
     * @param dialog
     * @returns {{url: *, width: *, height: *, aspectRatio: *, aspectRatioValue: *, fullwidth: *, preview: (*|Mixed|jQuery|HTMLElement)}}
     */
    function dialogFields(dialog) {
        return {
            id: dialog.getContentElement('schoolboxresource-tab', 'schoolboxresource-id'),
            type: dialog.getContentElement('schoolboxresource-tab', 'schoolboxresource-type'),
            url: dialog.getContentElement('schoolboxresource-tab', 'schoolboxresource-url'),
            filesystem: dialog.getContentElement('schoolboxresource-tab', 'schoolboxresource-filesystem'),
            preview: $('#schoolboxresource-preview'),
            width: dialog.getContentElement('schoolboxresource-tab', 'schoolboxresource-width'),
            height: dialog.getContentElement('schoolboxresource-tab', 'schoolboxresource-height'),
            aspectRatio: dialog.getContentElement('schoolboxresource-tab', 'schoolboxresource-aspectratio'),
            aspectRatioValue: dialog.getContentElement('schoolboxresource-tab', 'schoolboxresource-aspectratio-value'),
            fullwidth: dialog.getContentElement('schoolboxresource-tab', 'schoolboxresource-fullwidth'),
            alignment: dialog.getContentElement('schoolboxresource-tab', 'schoolboxresource-alignment'),
            border: dialog.getContentElement('schoolboxresource-tab', 'schoolboxresource-border'),
            verticalMargin: dialog.getContentElement('schoolboxresource-tab', 'schoolboxresource-verticalmargin'),
            horizontalMargin: dialog.getContentElement('schoolboxresource-tab', 'schoolboxresource-horizontalmargin')
        };
    }

    /**
     * Ignore non-numeric key presses
     * Allows (9) Tab character for field switching
     * Allows (8) Backspace character for modification
     * Allows (46) Delete character for modification
     * Allows (37) Left Arrow for navigation
     * Allows (39) Right Arrow for navigation
     *
     * @param event
     */
    function disableNonNumeric(event) {
        var allowedControlKeys = [8, 9, 46, 37, 39];

        /* Allow control keys
         */
        if (allowedControlKeys.indexOf(event.data.$.keyCode) > -1) {
            return;
        }
        /* Disallow non-numerics
         */
        else if (!numericOnly(event.data.$.keyCode)) {
            event.data.preventDefault();
        }
    }

    /**
     * Enable Constrain once both values provided
     * Disable Constrain once one dimension is omitted
     *
     * @param fields
     */
    function toggleConstrain(fields) {
        if (fields.width.getValue() && fields.height.getValue()) {
            fields.aspectRatio.enable();
        }
        else {
            fields.aspectRatio.disable();
        }
    }

    /**
     * The keypress was a tab character
     *
     * @param event
     * @returns {boolean}
     */
    function isTabCharacter(event) {
        return (event.data.$.keyCode == 9);
    }

    /**
     * Extract a particular css property's value from a style string
     *
     * @param property
     * @param style
     */
     function extractStyle(property, style) {
        if (style != null) {
            var allProperties = style.split(';');

            for (var i = 0; i < allProperties.length; i++) {
                var current = allProperties[i].split(':');

                /* Replace any spaces in the property name before checking
                 * as sometimes the style attribute is formatted
                 */
                if (current[0].replace(' ', '') == property) {
                    return current[1];
                }
            }
        }

        return '';
    }

    /**
     * Extract resource id from a url
     *
     * @param url
     * @returns {*}
     */
     function extractResourceId(url) {
        var bits = url.match(/id=([\d\w]+)/);

        if ((bits != null) && bits.length) {
            return bits[1];
        }

        return '';
    }

    /**
     * Extract resource filesystem type from a url
     *
     * @param url
     * @returns {*}
     */
    function extractResourceFilesystem(url) {
        var bits = url.match(/type=(\w+)/);

        if ((bits != null) && bits.length) {
            return bits[1];
        }

        return '';
    }

    /**
     * The keyCode pressed was a number
     *
     * @param keyCode
     * @returns {boolean}
     */
    function numericOnly(keyCode) {
        return ([48,49,50,51,52,53,54,55,56,57,96,97,98,99,100,101,102,103,104,105].indexOf(keyCode) > -1);
    }

    return {
        title: 'Embed Resource',
        minWidth: 400,
        minHeight: 200,

        /**
         * Tab Contents
         */
        contents: [
            {
                id: 'schoolboxresource-tab',
                label: 'Embed Resource',
                elements: [
                    {
                        type: 'hbox',
                        widths: ['80%', '20%'],
                        children: [
                            {
                                type: 'text',
                                id: 'schoolboxresource-url',
                                label: 'Url',
                                validate: CKEDITOR.dialog.validate.notEmpty( "Url cannot be empty." ),

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    /* Remember numeric id (for video and audio)
                                     */
                                    fields.id.setValue(extractResourceId(element.getAttribute('src')));

                                    /* Remember filesystem from which this thing came
                                     */
                                    fields.filesystem.setValue(element.getAttribute('data-schoolboxresource-filesystem'));

                                    /* Remember type
                                     */
                                    fields.type.setValue(element.getAttribute('data-schoolboxresource-type'));

                                    /* Remember url of the image or video/audio stream
                                     */
                                    if (fields.type.getValue() == 'video') {
                                        fields.url.setValue(element.getAttribute('data-schoolboxresource-source'));
                                    }
                                    else if (fields.type.getValue() == 'audio') {
                                        fields.url.setValue(element.getAttribute('data-schoolboxresource-source'));
                                    }
                                    else {
                                        fields.url.setValue(element.getAttribute('src'));
                                    }
                                },

                                /* When you type the url yourself, apply details
                                 */
                                onChange: function() {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);
                                    fields.parentSelector = dialog.getParentEditor().id
                                    /* Render image
                                     */
                                    var url = fields.url.getValue();

                                    /* Decide whether this is a video/audio resource
                                     */
                                    if (url.match(config.video.regex)) {
                                        var filesystem = extractResourceFilesystem(url);
                                        var id = extractResourceId(url);
                                        var tuple = filesystem + ':' + id;

                                        fields.id.setValue(extractResourceId(url));

                                        /* Detect difference between video and audio
                                         */
                                        $.getJSON(config.url.nodeinfo, { identifier: tuple }, function(responseText) {
                                            fields.type.setValue(responseText.type);
                                        });
                                    }
                                    /* Or whether it's just a local image
                                     */
                                    else if (url.match(config.image.regex)) {
                                        fields.type.setValue('image');
                                    }
                                    /* Or some other arbitrary url assumed to be some kind of image
                                     */
                                    else {
                                        fields.type.setValue('image');
                                    }
                                    render(fields, fields.url.getValue(), false);
                                }
                            },
                            {
                                type: 'button',
                                id: 'schoolboxresource-browse',
                                label: 'Choose File',
                                className: 'schoolboxresource-button',
                                onClick: function() {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    var domId = $(this).attr('domId');
                                    var previousModal = $('#'+domId).closest('.cke_dialog');
                                    previousModal.addClass('hide');

                                    /* Homepages have a hidden input field containing the homepage id
                                     * If that exists, use it; otherwise assume root node 0
                                     */
                                    var defaultFolderSelector = $('#categoryID').val() ? $('#categoryID').val() : config.defaultFolderSelector;

                                    /* Select something from the Resource Browser
                                     */
                                    $.fn.schoolboxResource({
                                        allowedFilesystems: 'files',
                                        allowedFiles: 'image/*,audio/*,video/*',
                                        previousModal: previousModal,
                                        useFolder: false,
                                        defaultFolderSelector: defaultFolderSelector
                                    });

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
                                                    fields.type.setValue(responseText.type);
                                                    fields.filesystem.setValue(responseText.filesystem);

                                                    /* Renderer Strategy (I guess)
                                                     */
                                                    if (fields.type.getValue() == 'video') {
                                                        fields.id.setValue(responseText.id);
                                                        fields.url.setValue(config.video.source(responseText.id, fields.filesystem.getValue()));
                                                    }
                                                    else if (fields.type.getValue() == 'audio') {
                                                        fields.id.setValue(responseText.id);
                                                        fields.url.setValue(config.audio.source(responseText.id, fields.filesystem.getValue()));
                                                    }
                                                    else if (fields.type.getValue() == 'image') {
                                                        fields.url.setValue(config.image.source(responseText.hash));
                                                    }

                                                    /* Prevent rendering attempt for uploaded files that
                                                     * aren't actually renderable
                                                     */
                                                    if (fields.type.getValue().length) {
                                                        render(fields, fields.url.getValue(), false);
                                                    }

                                                    /* Enable Constrain Aspect Ratio only if a width
                                                     * and height are provided (both are now optional)
                                                     *
                                                     * If optional, to re-activate Constrain, you must untick
                                                     * then re-tick the Constrain checkbox. Save some hassle
                                                     * by leaving it unticked when you don't specify a dimension
                                                     */
                                                    if (fields.width.getValue() && fields.height.getValue()) {
                                                        fields.aspectRatio.setValue(true);
                                                    }

                                                    /* Shut file picker modal, go back to Dialog
                                                     */
                                                    $('#file-picker').foundation('reveal', 'close');
                                                    $('#participant-customise-modal').css('z-index', '10000');

                                                });
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
                                }
                            }
                        ]
                    },
                    {
                        type: 'html',
                        html: '<label class="schoolboxresource-separator">Preview</label><iframe id="schoolboxresource-preview" class="schoolboxresource-preview" marginwidth="0" marginheight="0" hspace="0" vspace="0" frameborder="0"></iframe>'
                    },
                    {
                        type: 'html',
                        html: '<label class="schoolboxresource-separator">Dimensions</label>'
                    },
                    {
                        type: 'hbox',
                        widths: ['40%', '40%', '20%'],
                        children: [
                            {
                                type: 'text',
                                id: 'schoolboxresource-width',
                                label: 'Width',

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    /* Determine if Fullwidth should remain ticked
                                     */
                                    if (extractStyle('width', element.getAttribute('style')) != '100%') {
                                        var width = extractStyle('width', element.getAttribute('style')).replace('px', '');

                                        fields.width.setValue(width);
                                    }
                                },

                                /* If aspect ratio is active, recalculate dimensions upon change
                                 * Ignore Tab key presses entirely
                                 */
                                onKeyUp: function(event) {
                                    if (!isTabCharacter(event) && numericOnly(event.data.$.keyCode)) {
                                        var dialog = this.getDialog();
                                        var fields = dialogFields(dialog);

                                        /* Can no longer rely on there being an aspect ratio because
                                         * you can now leave either width or height empty; this causes
                                         * a non-numeric aspectRatioValue to exist
                                         */
                                        if (fields.aspectRatio.getValue() && fields.aspectRatioValue.getValue() && fields.width.getValue().length && fields.height.getValue().length) {
                                            var newHeight = Math.round(fields.width.getValue() / fields.aspectRatioValue.getValue());

                                            fields.height.setValue(newHeight);
                                        }

                                        /* Enable Constrain once both values provided
                                         */
                                        toggleConstrain(fields);
                                    }
                                },

                                /* Enable/Disable Constrain feature upon change
                                 */
                                onChange: function(event) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    toggleConstrain(fields);
                                },

                                /* Disable non-numeric characters
                                 */
                                onKeyDown: function(event) {
                                    disableNonNumeric(event);
                                }
                            },
                            {
                                type: 'text',
                                id: 'schoolboxresource-height',
                                label: 'Height',

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    var height = extractStyle('height', element.getAttribute('style')).replace('px', '');

                                    fields.height.setValue(height);
                                },

                                /* If aspect ratio is active, recalculate dimensions upon change
                                 * Ignore Tab key presses entirely
                                 */
                                onKeyUp: function(event) {
                                    if (!isTabCharacter(event)) {
                                        var dialog = this.getDialog();
                                        var fields = dialogFields(dialog);

                                        /* Can no longer rely on there being an aspect ratio because
                                         * you can now leave either width or height empty; this causes
                                         * a non-numeric aspectRatioValue to exist
                                         */
                                        if (fields.aspectRatio.getValue() && fields.aspectRatioValue.getValue() && fields.width.getValue().length && fields.height.getValue().length) {
                                            var newWidth = Math.round(fields.height.getValue() * fields.aspectRatioValue.getValue());

                                            fields.width.setValue(newWidth);
                                        }
                                    }
                                },

                                /* Enable/Disable Constrain feature upon change
                                 */
                                onChange: function(event) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    toggleConstrain(fields);
                                },

                                /* Disable non-numeric characters
                                 */
                                onKeyDown: function(event) {
                                    disableNonNumeric(event);
                                }
                            },
                            {
                                type: 'button',
                                id: 'schoolboxresource-resetdimensions',
                                label: 'Reset',
                                className: 'schoolboxresource-button',
                                onClick: function () {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    render(fields, fields.url.getValue(), true);
                                }
                            }
                        ]
                    },
                    {
                        type: 'text',
                        id: 'schoolboxresource-aspectratio-value',
                        label: 'Aspect Ratio Value',
                        className: 'hide'
                    },
                    {
                        type: 'checkbox',
                        id: 'schoolboxresource-aspectratio',
                        label: 'Constrain Aspect Ratio',

                        /* Editing an existing element
                         */
                        setup: function(element) {
                            var dialog = this.getDialog();
                            var fields = dialogFields(dialog);

                            /* Enable Constrain Aspect Ratio only if a width
                             * and height are provided (both are now optional)
                             *
                             * If optional, to re-activate Constrain, you must untick
                             * then re-tick the Constrain checkbox. Save some hassle
                             * by leaving it unticked when you don't specify a dimension
                             */
                            if (fields.width.getValue() && fields.height.getValue()) {
                                fields.aspectRatio.setValue(true);
                            }
                        },

                        /* Remember the ratio so we can apply it when
                         * Constrain Aspect Ratio is activated
                         */
                        onChange: function() {
                            var dialog = this.getDialog();
                            var fields = dialogFields(dialog);

                            aspectRatioReset(fields);
                        }
                    },
                    {
                        type: 'checkbox',
                        id: 'schoolboxresource-fullwidth',
                        label: 'Fullwidth',

                        /* Editing an existing element
                         */
                        setup: function(element) {
                            var dialog = this.getDialog();
                            var fields = dialogFields(dialog);

                            /* Determine if Fullwidth should remain ticked
                             */
                            var allClasses = [];

                            if (element.getAttribute('class').length) {
                                allClasses = element.getAttribute('class').split(' ');
                            }

                            /* If the full-width class existed
                             */
                            if (allClasses.indexOf('full-width') > -1) {
                                fields.fullwidth.setValue(true);
                            }
                            else {
                                fields.fullwidth.setValue(false);
                            }
                        }
                    },
                    {
                        type: 'html',
                        html: '<label class="schoolboxresource-separator">Appearance</label>'
                    },
                    {
                        type: 'hbox',
                        widths: ['33%', '33%', '33%'],
                        children: [
                            {
                                type: 'select',
                                id: 'schoolboxresource-alignment',
                                label: 'Alignment',
                                items: [['None', 'none'], ['Left', 'left'], ['Center', 'center'], ['Right', 'right']],
                                'default': 'none',

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    var float = extractStyle('float', element.getAttribute('style'));
                                    var margin = extractStyle('margin', element.getAttribute('style'));

                                    if (float == 'left' || float == 'right') {
                                        fields.alignment.setValue(float);
                                    }
                                    else if (margin == '0 auto') {
                                        fields.alignment.setValue('center');
                                    }
                                    else {
                                        fields.alignment.setValue('none');
                                    }
                                }
                            },
                            {
                                type: 'text',
                                id: 'schoolboxresource-verticalmargin',
                                label: 'Vertical Spacing',

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    var margin = extractStyle('margin-top', element.getAttribute('style')).replace('px', '');

                                    if (margin.length) {
                                        fields.verticalMargin.setValue(margin);
                                    }
                                },

                                /* Disable non-numeric characters
                                 */
                                onKeyDown: function(event) {
                                    disableNonNumeric(event);
                                }
                            },
                            {
                                type: 'text',
                                id: 'schoolboxresource-horizontalmargin',
                                label: 'Horizontal Spacing',

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    var marginLeft = extractStyle('margin-left', element.getAttribute('style')).replace('px', '');
                                    var marginRight = extractStyle('margin-right', element.getAttribute('style')).replace('px', '');

                                    if (marginLeft.length) {
                                        fields.horizontalMargin.setValue(marginLeft);
                                    }
                                    else if (marginRight.length) {
                                        fields.horizontalMargin.setValue(marginRight);
                                    }
                                },

                                /* Disable non-numeric characters
                                 */
                                onKeyDown: function(event) {
                                    disableNonNumeric(event);
                                }
                            }
                        ]
                    },
                    {
                        type: 'checkbox',
                        id: 'schoolboxresource-border',
                        label: 'Border',

                        /* Editing an existing element
                         */
                        setup: function(element) {
                            var dialog = this.getDialog();
                            var fields = dialogFields(dialog);

                            if (extractStyle('border', element.getAttribute('style')).length) {
                                fields.border.setValue(true);
                            }
                        }
                    },

                    /* Metadata lives at the bottom because they occupy space
                     * due to containing td element having padding
                     */

                    /* Store the file id here, because audio/video renders
                     * with a numeric id, not a hash, and has multiple
                     * urls for previews and content stream itself
                     *
                     * Not used for images
                     */
                    {
                        type: 'text',
                        id: 'schoolboxresource-id',
                        label: 'Id',
                        className: 'hide'
                    },

                    /* Store string 'image', 'video', 'audio'
                     * Used by various switch statements for rendering data
                     */
                    {
                        type: 'text',
                        id: 'schoolboxresource-type',
                        label: 'Type',
                        className: 'hide'
                    },

                    /* The filesystem where the resource was found
                     */
                    {
                        type: 'text',
                        id: 'schoolboxresource-filesystem',
                        label: 'Filesystem',
                        className: 'hide'
                    }
                ]
            }
        ],

        /**
         * Manufacture html and insert into editor
         */
        onOk: function() {
            var dialog = this;
            var fields = dialogFields(dialog);

            editorInstance.insertElement(inject(editorInstance, fields, fields.url.getValue()));
            editorInstance.insertHtml('<p>&nbsp;</p>');
            $('#participant-customise-modal').css('z-index', '10000');
        },

        /**
         * Dialog startup
         *
         * Occurs when you click the Schoolbox Media button regardless
         * of what you have currently selected
         */
        onShow: function() {
            var fields = dialogFields(this);

            /* Clear any existing preview that might have been loaded
             * This is required when you have inserted an image and
             * then go to insert a new one; it remembers the preview
             * from before
             */
            fields.preview.contents().find('html').html('');

            /* Deal with existing/inserting new elements
             */
            var selection = editorInstance.getSelection();
            var element = selection.getStartElement();

            if (!element || element.getName() != 'img') {
                element = editorInstance.document.createElement('img');
                this.insertMode = true;
            }
            else {
                this.insertMode = false;
            }

            this.element = element;

            if (!this.insertMode) {
                this.setupContent(this.element);
            }
        }
    };
});
