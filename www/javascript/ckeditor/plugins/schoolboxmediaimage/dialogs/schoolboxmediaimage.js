CKEDITOR.dialog.add('schoolboxmediaImageDialog', function (editorInstance) {
    return {
        title: 'Schoolbox Media Image',
        minWidth: 400,
        minHeight: 200,

        /**
         * Tab Contents
         */
        contents: [
            {
                id: 'schoolboxmedia-image-tab',
                label: 'Schoolbox Media Image',
                elements: [
                    {
                        type: 'hbox',
                        widths: ['80%', '20%'],
                        children: [
                            {
                                type: 'text',
                                id: 'schoolboxmedia-image-url',
                                label: 'Url',
                                validate: CKEDITOR.dialog.validate.notEmpty( "Url cannot be empty." ),

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxMedia_Image_DialogFields(dialog);

                                    fields.url.setValue(element.getAttribute('src'));
                                },

                                /* When you type the url yourself, apply details
                                 */
                                onChange: function() {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxMedia_Image_DialogFields(dialog);

                                    var image = true;

                                    /* Render image
                                     * Reset aspect ratio in case it remembered from previous image
                                     *
                                     * Bypass infinite loop with flag, due to Browse button inflicting onChange
                                     * event by programmatically assigning url value to this input field
                                     */
                                    if (image) {
                                        $.fn.schoolboxMedia_Image_Render(fields.url.getValue(), dialog, false);
                                    }
                                }
                            },
                            {
                                type: 'button',
                                id: 'schoolboxmedia-image-browse',
                                label: 'Browse',
                                className: 'schoolboxmedia-button',
                                onClick: function() {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxMedia_Image_DialogFields(dialog);

                                    /* Select something from the Resource Browser
                                     */
                                    var message = $.fn.schoolboxMedia();

                                    $(window).on('message', function(event) {
                                        var message;

                                        try {
                                            message = JSON.parse(event.originalEvent.data);
                                        } catch (err) {
                                            message = event.originalEvent.data;
                                        }

                                        /* Find out how to render this file
                                         */
                                        $.getJSON('/editor/nodeInfo.php', { hash: message.data[0].hash }, function(responseText) {

                                            /* Renderer Strategy (I guess)
                                             */
                                            fields.url.setValue('/storage/image.php?hash='+responseText.hash);

                                            /* Shut modal, go back to Dialog
                                             */
                                            $('[data-schoolboxmedia-reveal]').foundation('reveal', 'close');
                                        });
                                    });
                                }
                            }
                        ]
                    },
                    {
                        type: 'html',
                        html: '<label class="schoolboxmedia-separator">Preview</label><div id="schoolboxmedia-image-preview" class="schoolboxmedia-image-preview"></div>'
                    },
                    {
                        type: 'html',
                        html: '<label class="schoolboxmedia-separator">Dimensions</label>'
                    },
                    {
                        type: 'hbox',
                        widths: ['40%', '40%', '20%'],
                        children: [
                            {
                                type: 'text',
                                id: 'schoolboxmedia-image-width',
                                label: 'Width',
                                validate: CKEDITOR.dialog.validate.notEmpty( "Width cannot be empty." ),

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxMedia_Image_DialogFields(dialog);

                                    /* Determine if Fullwidth should remain ticked
                                     */
                                    if ($.fn.schoolboxMedia_Image_ExtractStyle('width', element.getAttribute('style')) != '100%') {
                                        var width = $.fn.schoolboxMedia_Image_ExtractStyle('width', element.getAttribute('style')).replace('px', '');

                                        fields.width.setValue(width);
                                    }
                                },

                                /* If aspect ratio is active, recalculate dimensions upon change
                                 * Ignore Tab key presses entirely
                                 */
                                onKeyUp: function(event) {
                                    if (event.data.$.keyCode != 9) {
                                        var dialog = this.getDialog();
                                        var fields = $.fn.schoolboxMedia_Image_DialogFields(dialog);

                                        if (fields.aspectRatio.getValue()) {
                                            var newHeight = Math.round(fields.width.getValue() / fields.aspectRatioValue.getValue());

                                            fields.height.setValue(newHeight);
                                        }
                                    }
                                }
                            },
                            {
                                type: 'text',
                                id: 'schoolboxmedia-image-height',
                                label: 'Height',
                                validate: CKEDITOR.dialog.validate.notEmpty( "Height cannot be empty."),

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxMedia_Image_DialogFields(dialog);

                                    var height = $.fn.schoolboxMedia_Image_ExtractStyle('height', element.getAttribute('style')).replace('px', '');

                                    fields.height.setValue(height);
                                },

                                /* If aspect ratio is active, recalculate dimensions upon change
                                 * Ignore Tab key presses entirely
                                 */
                                onKeyUp: function(event) {
                                    if (event.data.$.keyCode != 9) {
                                        var dialog = this.getDialog();
                                        var fields = $.fn.schoolboxMedia_Image_DialogFields(dialog);

                                        if (fields.aspectRatio.getValue()) {
                                            var newWidth = Math.round(fields.height.getValue() * fields.aspectRatioValue.getValue());

                                            fields.width.setValue(newWidth);
                                        }
                                    }
                                }
                            },
                            {
                                type: 'button',
                                id: 'schoolboxmedia-image-resetdimensions',
                                label: 'Reset',
                                className: 'schoolboxmedia-button',
                                onClick: function () {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxMedia_Image_DialogFields(dialog);

                                    $.fn.schoolboxMedia_Image_Render(fields.url.getValue(), dialog, true);
                                }
                            }
                        ]
                    },
                    {
                        type: 'text',
                        id: 'schoolboxmedia-image-aspectratio-value',
                        label: 'Aspect Ratio Value',
                        className: 'hide'
                    },
                    {
                        type: 'checkbox',
                        id: 'schoolboxmedia-image-aspectratio',
                        label: 'Constrain Aspect Ratio',

                        /* Remember the ratio so we can apply it when
                         * Constrain Aspect Ratio is activated
                         */
                        onChange: function() {
                            var dialog = this.getDialog();

                            $.fn.schoolboxMedia_Image_AspectRatioReset(dialog);
                        }
                    },
                    {
                        type: 'checkbox',
                        id: 'schoolboxmedia-image-fullwidth',
                        label: 'Fullwidth',

                        /* Editing an existing element
                         */
                        setup: function(element) {
                            var dialog = this.getDialog();
                            var fields = $.fn.schoolboxMedia_Image_DialogFields(dialog);

                            /* Determine if Fullwidth should remain ticked
                             */
                            if ($.fn.schoolboxMedia_Image_ExtractStyle('width', element.getAttribute('style')) == '100%') {
                                fields.fullwidth.setValue(true);
                            }
                            else {
                                fields.fullwidth.setValue(false);
                            }
                        }
                    },
                    {
                        type: 'html',
                        html: '<label class="schoolboxmedia-separator">Appearance</label>'
                    },
                    {
                        type: 'hbox',
                        widths: ['33%', '33%', '33%'],
                        children: [
                            {
                                type: 'select',
                                id: 'schoolboxmedia-image-alignment',
                                label: 'Alignment',
                                items: [['None', 'none'], ['Left', 'left'], ['Center', 'center'], ['Right', 'right']],
                                'default': 'none',

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxMedia_Image_DialogFields(dialog);

                                    var float = $.fn.schoolboxMedia_Image_ExtractStyle('float', element.getAttribute('style'));
                                    var margin = $.fn.schoolboxMedia_Image_ExtractStyle('margin', element.getAttribute('style'));

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
                                id: 'schoolboxmedia-image-verticalpadding',
                                label: 'Vertical Spacing',

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxMedia_Image_DialogFields(dialog);

                                    var padding = $.fn.schoolboxMedia_Image_ExtractStyle('padding-top', element.getAttribute('style')).replace('px', '');

                                    if (padding.length) {
                                        fields.verticalPadding.setValue(padding);
                                    }
                                }
                            },
                            {
                                type: 'text',
                                id: 'schoolboxmedia-image-horizontalpadding',
                                label: 'Horizontal Spacing',

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxMedia_Image_DialogFields(dialog);

                                    var padding = $.fn.schoolboxMedia_Image_ExtractStyle('padding-left', element.getAttribute('style')).replace('px', '');

                                    if (padding.length) {
                                        fields.horizontalPadding.setValue(padding);
                                    }
                                }
                            }
                        ]
                    },
                    {
                        type: 'checkbox',
                        id: 'schoolboxmedia-image-border',
                        label: 'Border',

                        /* Editing an existing element
                         */
                        setup: function(element) {
                            var dialog = this.getDialog();
                            var fields = $.fn.schoolboxMedia_Image_DialogFields(dialog);

                            if ($.fn.schoolboxMedia_Image_ExtractStyle('border', element.getAttribute('style')).length) {
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

            editorInstance.insertElement($.fn.schoolboxMedia_Image_Inject(editorInstance, dialog));
        },

        /**
         * Dialog startup
         *
         * Occurs when you click the Schoolbox Media button regardless
         * of what you have currently selected
         */
        onShow: function() {
            var fields = $.fn.schoolboxMedia_Image_DialogFields(this);

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
