CKEDITOR.dialog.add('schoolboxmediaDialog', function (editorInstance) {
    return {
        title: 'Schoolbox Media',
        minWidth: 400,
        minHeight: 200,

        /**
         * Tab Contents
         */
        contents: [
            {
                id: 'schoolboxmedia-image-tab',
                label: 'Schoolbox Image',
                elements: [
                    {
                        type: 'hbox',
                        widths: ['67%', '33%'],
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
                                    var fields = $.fn.schoolboxMediaDialogFields(dialog);

                                    fields.url.setValue(element.getAttribute('src'));
                                },

                                /* When you type the url yourself, apply details
                                 */
                                onChange: function() {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxMediaDialogFields(dialog);

                                    var image = true;

                                    /* Render image
                                     * Reset aspect ratio in case it remembered from previous image
                                     *
                                     * Bypass infinite loop with flag, due to Browse button inflicting onChange
                                     * event by programmatically assigning url value to this input field
                                     */
                                    if (image) {
                                        $.fn.schoolboxMediaRenderImage(fields.url.getValue(), dialog, false);
                                    }
                                }
                            },
                            {
                                type: 'button',
                                id: 'schoolboxmedia-image-browse',
                                label: editorInstance.lang.common.browseServer,
                                className: 'schoolboxmedia-browse',
                                onClick: function() {
                                    var dialog = this.getDialog();

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
                                            $.fn.schoolboxMediaFactory(responseText.type, responseText, dialog);

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
                        html: '<label class="schoolboxmedia-separator">Image Dimensions</label>'
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
                                    var fields = $.fn.schoolboxMediaDialogFields(dialog);

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
                                        var fields = $.fn.schoolboxMediaDialogFields(dialog);

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
                                    var fields = $.fn.schoolboxMediaDialogFields(dialog);

                                    var height = extractStyle('height', element.getAttribute('style')).replace('px', '');

                                    fields.height.setValue(height);
                                },

                                /* If aspect ratio is active, recalculate dimensions upon change
                                 * Ignore Tab key presses entirely
                                 */
                                onKeyUp: function(event) {
                                    if (event.data.$.keyCode != 9) {
                                        var dialog = this.getDialog();

                                        var fields = $.fn.schoolboxMediaDialogFields(dialog);

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
                                className: 'schoolboxmedia-browse',
                                onClick: function () {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxMediaDialogFields(dialog);

                                    $.fn.schoolboxMediaRenderImage(fields.url.getValue(), dialog, true);
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

                            $.fn.schoolboxMediaAspectRatioReset(dialog);
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
                            var fields = $.fn.schoolboxMediaDialogFields(dialog);

                            /* Determine if Fullwidth should remain ticked
                             */
                            if (extractStyle('width', element.getAttribute('style')) == '100%') {
                                fields.fullwidth.setValue(true);
                            }
                            else {
                                fields.fullwidth.setValue(false);
                            }
                        }
                    },
                    {
                        type: 'html',
                        html: '<label class="schoolboxmedia-separator">Decorations</label>'
                    },
                    {
                        type: 'checkbox',
                        id: 'schoolboxmedia-image-border',
                        label: 'Border',

                        /* Editing an existing element
                         */
                        setup: function(element) {
                            var dialog = this.getDialog();
                            var fields = $.fn.schoolboxMediaDialogFields(dialog);

                            if (extractStyle('border', element.getAttribute('style')).length) {
                                fields.border.setValue(true);
                            }
                        }
                    },
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
                            var fields = $.fn.schoolboxMediaDialogFields(dialog);

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
                    }
                ]
            }
        ],

        /**
         * Manufacture html and insert into editor
         */
        onOk: function() {
            var dialog = this;

            editorInstance.insertElement($.fn.schoolboxMediaInjectImage(editorInstance, dialog));
        },

        /**
         * Dialog startup
         *
         * Occurs when you click the Schoolbox Media button regardless
         * of what you have currently selected
         */
        onShow: function() {
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
