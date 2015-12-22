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

                                /* When you type the url yourself, apply details
                                 */
                                onBlur: function() {
                                    var dialog = this.getDialog();
                                    var fields = $.fn.schoolboxMediaDialogFields(dialog);

                                    var image = true;

                                    /* Render image
                                     * Reset aspect ratio in case it remembered from previous image
                                     */
                                    if (image) {
                                        $.fn.schoolboxMediaRenderImage(fields.url.getValue(), dialog);
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
                        label: 'Fullwidth'
                    },
                    {
                        type: 'html',
                        html: '<label class="schoolboxmedia-separator">Decorations</label>'
                    },
                    {
                        type: 'checkbox',
                        id: 'schoolboxmedia-image-border',
                        label: 'Border'
                    },
                    {
                        type: 'select',
                        id: 'schoolboxmedia-image-alignment',
                        label: 'Alignment',
                        items: [ ['None', 'none'], ['Left', 'left'], ['Center', 'center'], ['Right', 'right']],
                        'default': 'none'
                    }
                ]
            }
        ],

        /**
         * Manufacture html and insert into editor
         */
        onOk: function() {
            var dialog = this;
            var fields = $.fn.schoolboxMediaDialogFields(dialog);

            var styles = [];

            var element = editorInstance.document.createElement('img');
            element.setAttribute('src', fields.url.getValue());

            /* Determine width - if fullwidth checked, use 100%
             * otherwise use specified values. Note that width:100%
             * is not allowed, so use the usual class instead
             */
            if (fields.fullwidth.getValue()) {
                styles.push('width:100%');
            }
            else {
                styles.push('width:'+fields.width.getValue()+'px');
                styles.push('height:'+fields.height.getValue()+'px');
            }

            /* Alignment
             * Floats left and right;
             * centered uses 0 auto;
             * none is no floating, just display inline
             */
            switch (fields.alignment.getValue()) {
                case 'center':
                    styles.push('margin: 0 auto');
                    styles.push('display: block');
                    break;

                case 'left':
                case 'right':
                    styles.push('float:' + fields.alignment.getValue());
                    break;

                default:
                    break;
            }

            /* Border
             */
            if (fields.border.getValue()) {
                styles.push('border: 1px solid #808080;');
            }

            /* Combine and inject
             */
            element.setAttribute('style', styles.join(';'));

            editorInstance.insertElement(element);
        }
    };
});
