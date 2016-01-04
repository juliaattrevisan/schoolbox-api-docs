CKEDITOR.dialog.add('schoolboxresourceMediaDialog', function (editorInstance) {
    return {
        title: 'Schoolbox Resource Media',
        minWidth: 400,
        minHeight: 200,

        /**
         * Tab Contents
         */
        contents: [
            {
                id: 'schoolboxresource-media-tab',
                label: 'Schoolbox Resource Media',
                elements: [

                    /* Store the file id here, because audio/video renders
                     * with a numeric id, not a hash, and has multiple
                     * urls for previews and content stream itself
                     */
                    {
                        type: 'text',
                        id: 'schoolboxresource-media-id',
                        label: 'Id',
                        className: 'hide'
                    },
                    {
                        type: 'hbox',
                        widths: ['80%', '20%'],
                        children: [
                            {
                                type: 'text',
                                id: 'schoolboxresource-media-url',
                                label: 'Url',
                                validate: CKEDITOR.dialog.validate.notEmpty("Url cannot be empty."),

                                /* Editing an existing element
                                 */
                                setup: function (element) {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxResource_Media_DialogFields(dialog);

                                    fields.url.setValue(element.getAttribute('src'));
                                },

                                /* When you type the url yourself, apply details
                                 */
                                onChange: function () {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxResource_Media_DialogFields(dialog);

                                    /* Render media
                                     */
                                    $.fn.schoolboxResource_Media_Render(fields, dialog, false);
                                }
                            },
                            {
                                type: 'button',
                                id: 'schoolboxresource-media-browse',
                                label: 'Browse',
                                className: 'schoolboxresource-button',
                                onClick: function () {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxResource_Media_DialogFields(dialog);

                                    /* Select something from the Resource Browser
                                     */
                                    var message = $.fn.schoolboxResource();

                                    $(window).on('message', function (event) {
                                        var message;

                                        try {
                                            message = JSON.parse(event.originalEvent.data);
                                        } catch (err) {
                                            message = event.originalEvent.data;
                                        }

                                        /* Find out how to render this file
                                         */
                                        $.getJSON('/editor/nodeInfo.php', {hash: message.data[0].hash}, function (responseText) {

                                            /* Renderer Strategy (I guess)
                                             */
                                            fields.id.setValue(responseText.id);
                                            fields.url.setValue('/cms/videoThumb.php?type=sbfs&id=' + responseText.id);

                                            /* Shut modal, go back to Dialog
                                             */
                                            $('[data-schoolboxresource-reveal]').foundation('reveal', 'close');
                                        });
                                    });
                                }
                            }
                        ]
                    },
                    {
                        type: 'html',
                        html: '<label class="schoolboxresource-separator">Preview</label><div id="schoolboxresource-media-preview" class="schoolboxresource-media-preview"></div>'
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
                                id: 'schoolboxresource-media-width',
                                label: 'Width',
                                validate: CKEDITOR.dialog.validate.notEmpty( "Width cannot be empty." ),

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxResource_Media_DialogFields(dialog);

                                    /* Determine if Fullwidth should remain ticked
                                     */
                                    if ($.fn.schoolboxResource_Image_ExtractStyle('width', element.getAttribute('style')) != '100%') {
                                        var width = $.fn.schoolboxResource_Image_ExtractStyle('width', element.getAttribute('style')).replace('px', '');

                                        fields.width.setValue(width);
                                    }
                                },

                                /* If aspect ratio is active, recalculate dimensions upon change
                                 * Ignore Tab key presses entirely
                                 */
                                onKeyUp: function(event) {
                                    if (event.data.$.keyCode != 9) {
                                        var dialog = this.getDialog();
                                        var fields = $.fn.schoolboxResource_Media_DialogFields(dialog);

                                        if (fields.aspectRatio.getValue()) {
                                            var newHeight = Math.round(fields.width.getValue() / fields.aspectRatioValue.getValue());

                                            fields.height.setValue(newHeight);
                                        }
                                    }
                                }
                            },
                            {
                                type: 'text',
                                id: 'schoolboxresource-media-height',
                                label: 'Height',
                                validate: CKEDITOR.dialog.validate.notEmpty( "Height cannot be empty."),

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxResource_Media_DialogFields(dialog);

                                    var height = $.fn.schoolboxResource_Image_ExtractStyle('height', element.getAttribute('style')).replace('px', '');

                                    fields.height.setValue(height);
                                },

                                /* If aspect ratio is active, recalculate dimensions upon change
                                 * Ignore Tab key presses entirely
                                 */
                                onKeyUp: function(event) {
                                    if (event.data.$.keyCode != 9) {
                                        var dialog = this.getDialog();
                                        var fields = $.fn.schoolboxResource_Media_DialogFields(dialog);

                                        if (fields.aspectRatio.getValue()) {
                                            var newWidth = Math.round(fields.height.getValue() * fields.aspectRatioValue.getValue());

                                            fields.width.setValue(newWidth);
                                        }
                                    }
                                }
                            },
                            {
                                type: 'button',
                                id: 'schoolboxresource-media-resetdimensions',
                                label: 'Reset',
                                className: 'schoolboxresource-button',
                                onClick: function () {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxResource_Media_DialogFields(dialog);

                                    $.fn.schoolboxResource_Media_Render(fields.id.getValue(), dialog, true);
                                }
                            }
                        ]
                    },
                    {
                        type: 'text',
                        id: 'schoolboxresource-media-aspectratio-value',
                        label: 'Aspect Ratio Value',
                        className: 'hide'
                    },
                    {
                        type: 'checkbox',
                        id: 'schoolboxresource-media-aspectratio',
                        label: 'Constrain Aspect Ratio',

                        /* Remember the ratio so we can apply it when
                         * Constrain Aspect Ratio is activated
                         */
                        onChange: function() {
                            var dialog = this.getDialog();
                            var fields = $.fn.schoolboxResource_Media_DialogFields(dialog);

                            $.fn.schoolboxResource_Media_AspectRatioReset(fields);
                        }
                    },

                    {
                        type: 'checkbox',
                        id: 'schoolboxresource-media-fullwidth',
                        label: 'Fullwidth',

                        /* Editing an existing element
                         */
                        setup: function(element) {
                            var dialog = this.getDialog();
                            var fields = $.fn.schoolboxResource_Media_DialogFields(dialog);

                            /* Determine if Fullwidth should remain ticked
                             */
                            if ($.fn.schoolboxResource_Image_ExtractStyle('width', element.getAttribute('style')) == '100%') {
                                fields.fullwidth.setValue(true);
                            }
                            else {
                                fields.fullwidth.setValue(false);
                            }
                        }
                    },
                ]
            }
        ],

        /**
         * Manufacture html and insert into editor
         */
        onOk: function() {
            var dialog = this;
            var fields = $.fn.schoolboxResource_Media_DialogFields(dialog);

            editorInstance.insertElement($.fn.schoolboxResource_Media_Inject(editorInstance, fields));
        },

        /**
         * Dialog startup
         *
         * Occurs when you click the Schoolbox Media button regardless
         * of what you have currently selected
         */
        onShow: function() {
            var fields = $.fn.schoolboxResource_Media_DialogFields(this);

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

            if (!element || element.getName() != 'video') {
                element = editorInstance.document.createElement('video');
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
    }
});

