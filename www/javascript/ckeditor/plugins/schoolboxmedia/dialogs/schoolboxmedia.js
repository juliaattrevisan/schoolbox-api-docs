CKEDITOR.dialog.add('schoolboxmediaDialog', function (editorInstance) {
    return {
        title: 'Schoolbox Media',
        minWidth: 400,
        minHeight: 200,

        contents: [
            {
                id: 'schoolboxmedia-image-tab',
                label: 'Schoolbox Image',
                elements: [
                    {
                        type: 'text',
                        id: 'schoolboxmedia-image-url',
                        label: 'Url',
                        validate: CKEDITOR.dialog.validate.notEmpty( "Url cannot be empty." ),

                        /* When you type the url yourself, apply details
                         */
                        onBlur: function() {
                            var self = this;

                            var image = true;

                            if (image) {
                                var url = self.getDialog().getContentElement('schoolboxmedia-image-tab', 'schoolboxmedia-image-url').getValue();

                                $.fn.schoolboxMediaResourceBrowserRenderImage(url, self.getDialog());
                            }
                        }
                    },
                    {
                        type: 'button',
                        id: 'schoolboxmedia-image-browse',
                        label: editorInstance.lang.common.browseServer,
                        onClick: function() {
                            var self = this;

                            /* Select something from the Resource Browser
                             */
                            var message = $.fn.schoolboxMediaResourceBrowser();

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
                                    $.fn.schoolboxMediaResourceBrowserRenderer(responseText.type, responseText, self.getDialog());

                                    /* Shut modal, go back to Dialog
                                     */
                                    $('[data-schoolboxmedia-reveal]').foundation('reveal', 'close');
                                });
                            });
                        }
                    },
                    {
                        type: 'html',
                        html: '<label class="cke_label_separator">Preview</label><div id="schoolboxmedia-image-preview" class="schoolboxmedia-image-preview"></div>'
                    },
                    {
                        type: 'html',
                        html: '<label class="cke_label_separator">Image Dimensions</label>'
                    },
                    {
                        type: 'hbox',
                        widths: ['50%', '50%'],
                        children: [
                            {
                                type: 'text',
                                id: 'schoolboxmedia-image-width',
                                label: 'Width',
                                validate: CKEDITOR.dialog.validate.notEmpty( "Width cannot be empty." ),

                                /* If aspect ratio is active, recalculate dimensions upon change
                                 * Ignore Tab key presses entirely
                                 */
                                onKeyUp: function(event) {
                                    if (event.data.$.keyCode != 9) {
                                        var self = this;

                                        var fields = $.fn.schoolboxMediaResourceBrowserDialogFields(self.getDialog());

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

                                /* If aspect ratio is active, recalculate dimensions upon change
                                 * Ignore Tab key presses entirely
                                 */
                                onKeyUp: function(event) {
                                    if (event.data.$.keyCode != 9) {
                                        var self = this;

                                        var fields = $.fn.schoolboxMediaResourceBrowserDialogFields(self.getDialog());

                                        if (fields.aspectRatio.getValue()) {
                                            var newWidth = Math.round(fields.height.getValue() * fields.aspectRatioValue.getValue());

                                            fields.width.setValue(newWidth);
                                        }
                                    }
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
                            var self = this;

                            var fields = $.fn.schoolboxMediaResourceBrowserDialogFields(self.getDialog());

                            fields.aspectRatioValue.setValue(fields.width.getValue()/fields.height.getValue());
                        }
                    },
                    {
                        type: 'checkbox',
                        id: 'schoolboxmedia-image-fullwidth',
                        label: 'Fullwidth'
                    }
                ]
            }
        ]
    };
});
