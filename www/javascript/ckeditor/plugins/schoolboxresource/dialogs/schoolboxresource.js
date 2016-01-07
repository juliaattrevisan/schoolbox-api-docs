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
            thumbnail: '/cms/videoThumb.php?type=sbfs&size=poster&id=',
            regex: /^\/cms\/sendVideo/
        },

        /* Audio urls and regex
         */
        audio: {
            source: function(id, filesystem) {
                return '/cms/sendVideo.php?id=' + id + '&type=' + filesystem;
            },
            thumbnail: '/images/core/component/audio-thumbnail-processing.gif',
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
        }
    };

    /**
     * Populate dialog fields with image data
     *
     * @param fields
     * @param url
     * @param reset
     */
    function render(fields, url, reset) {

        /* Special case for video url
         */
        if (fields.type.getValue() == 'video') {
            url = config.video.thumbnail + fields.id.getValue();
        }
        if (fields.type.getValue() == 'audio') {
            url = config.audio.thumbnail;
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
            var preview = '<img src="'+url+'" />';

            /* Set dialog input contents
             */
            fields.preview.html(preview);

            /* Don't change width or height if they were already set
             *
             * This occurs when setup() runs, when editing an existing image
             * Or when an image was set to Fullwidth (it won't have a width/height style attribute)
             */
            if (fields.width.getValue() == '' || fields.height.getValue() == '' || reset) {
                fields.width.setValue(width);
                fields.height.setValue(height);
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
        fields.aspectRatioValue.setValue(fields.width.getValue()/fields.height.getValue());
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
         */
        if (fields.type.getValue() == 'video') {
            url = config.video.thumbnail + fields.id.getValue();

            dataVars = {
                'data-schoolboxresource-type': 'video',
                'data-schoolboxresource-filesystem': fields.filesystem.getValue(),
                'data-schoolboxresource-thumbnail': url,
                'data-schoolboxresource-source': config.video.source(fields.id.getValue(), fields.filesystem.getValue())
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
                'data-schoolboxresource-source': config.audio.source(fields.id.getValue(), fields.filesystem.getValue())
            };
        }
        /* Images render with provided information
         */
        else if (fields.type.getValue() == 'image') {
            dataVars = {
                'data-schoolboxresource-type': 'image',
                'data-schoolboxresource-filesystem': fields.filesystem.getValue(),
                'data-schoolboxresource-thumbnail': url,
                'data-schoolboxresource-source': url
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
         * Include both styles and clases to display this; because
         * of htmlpurifier and ckeditor iframe respectively
         */
        if (fields.fullwidth.getValue()) {
            classes.push('full-width');
            styles.push('width:100%');
        }
        else {
            styles.push('width:' + fields.width.getValue() + 'px');
            styles.push('height:' + fields.height.getValue() + 'px');
        }

        /* Alignment
         * Floats left and right;
         * centered uses 0 auto;
         * none is no floating, just display inline
         */
        if (fields.alignment != undefined) {
            switch (fields.alignment.getValue()) {
                case 'center':
                    styles.push('margin:0 auto');
                    styles.push('display:block');
                    break;

                case 'left':
                case 'right':
                    styles.push('float:' + fields.alignment.getValue());
                    break;

                default:
                    break;
            }
        }

        /* Border
         */
        if (fields.border.getValue() != '') {
            styles.push('border:1px solid #808080;');
        }

        /* Vertical Spacing (applies padding-top and padding-bottom)
         */
        if (fields.verticalPadding.getValue() != '') {
            styles.push('padding-top:' + fields.verticalPadding.getValue() + 'px');
            styles.push('padding-bottom:' + fields.verticalPadding.getValue() + 'px');
        }

        /* Horizontal Spacing (applies padding-left and padding-right)
         */
        if (fields.horizontalPadding.getValue() != '') {
            styles.push('padding-left:' + fields.horizontalPadding.getValue() + 'px');
            styles.push('padding-right:' + fields.horizontalPadding.getValue() + 'px');
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
            verticalPadding: dialog.getContentElement('schoolboxresource-tab', 'schoolboxresource-verticalpadding'),
            horizontalPadding: dialog.getContentElement('schoolboxresource-tab', 'schoolboxresource-horizontalpadding')
        };
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

    return {
        title: 'Schoolbox Resource Image',
        minWidth: 400,
        minHeight: 200,

        /**
         * Tab Contents
         */
        contents: [
            {
                id: 'schoolboxresource-tab',
                label: 'Schoolbox Resource Image',
                elements: [

                    /* Store the file id here, because audio/video renders
                     * with a numeric id, not a hash, and has multiple
                     * urls for previews and content stream itself
                     *
                     * Not used for images
                     */
                    {
                        type: 'text',
                        id: 'schoolboxresource-id',
                        label: 'Id'
                    },

                    /* Store string 'image', 'video', 'audio'
                     * Used by various switch statements for rendering data
                     */
                    {
                        type: 'text',
                        id: 'schoolboxresource-type',
                        label: 'Type'
                    },

                    /* The filesystem where the resource was found
                     */
                    {
                        type: 'text',
                        id: 'schoolboxresource-filesystem',
                        label: 'Filesystem'
                    },
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
                                label: 'Browse',
                                className: 'schoolboxresource-button',
                                onClick: function() {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    /* Select something from the Resource Browser
                                     */
                                    $.fn.schoolboxResource();

                                    $(window).on('message', function(event) {
                                        var message;

                                        try {
                                            message = JSON.parse(event.originalEvent.data);
                                        } catch (err) {
                                            message = event.originalEvent.data;
                                        }

                                        /* Find out how to render this file
                                         */
                                        if (message.data != undefined) {
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

                                                render(fields, fields.url.getValue(), false);

                                                /* Shut modal, go back to Dialog
                                                 */
                                                $('[data-schoolboxresource-reveal]').foundation('reveal', 'close');
                                            });
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
                        html: '<label class="schoolboxresource-separator">Preview</label><div id="schoolboxresource-preview" class="schoolboxresource-preview"></div>'
                    },
                    {
                        type: 'html',
                        html: '<label class="schoolboxresource-separator">Dimensions</label><p>Note: Audio resources always display at full width and thus ignore<br />all width/height dimensions.</p>'
                    },
                    {
                        type: 'hbox',
                        widths: ['40%', '40%', '20%'],
                        children: [
                            {
                                type: 'text',
                                id: 'schoolboxresource-width',
                                label: 'Width',
                                validate: CKEDITOR.dialog.validate.notEmpty( "Width cannot be empty." ),

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
                                    if (event.data.$.keyCode != 9) {
                                        var dialog = this.getDialog();
                                        var fields = dialogFields(dialog);

                                        if (fields.aspectRatio.getValue()) {
                                            var newHeight = Math.round(fields.width.getValue() / fields.aspectRatioValue.getValue());

                                            fields.height.setValue(newHeight);
                                        }
                                    }
                                }
                            },
                            {
                                type: 'text',
                                id: 'schoolboxresource-height',
                                label: 'Height',
                                validate: CKEDITOR.dialog.validate.notEmpty( "Height cannot be empty."),

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
                                    if (event.data.$.keyCode != 9) {
                                        var dialog = this.getDialog();
                                        var fields = dialogFields(dialog);

                                        if (fields.aspectRatio.getValue()) {
                                            var newWidth = Math.round(fields.height.getValue() * fields.aspectRatioValue.getValue());

                                            fields.width.setValue(newWidth);
                                        }
                                    }
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
                            if (element.getAttribute('class') == 'full-width') {
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
                                id: 'schoolboxresource-verticalpadding',
                                label: 'Vertical Spacing',

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    var padding = extractStyle('padding-top', element.getAttribute('style')).replace('px', '');

                                    if (padding.length) {
                                        fields.verticalPadding.setValue(padding);
                                    }
                                }
                            },
                            {
                                type: 'text',
                                id: 'schoolboxresource-horizontalpadding',
                                label: 'Horizontal Spacing',

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    var padding = extractStyle('padding-left', element.getAttribute('style')).replace('px', '');

                                    if (padding.length) {
                                        fields.horizontalPadding.setValue(padding);
                                    }
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
            fields.preview.html('');

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
