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

                                    var image = true;

                                    /* Render image
                                     * Reset aspect ratio in case it remembered from previous image
                                     *
                                     * Bypass infinite loop with flag, due to Browse button inflicting onChange
                                     * event by programmatically assigning url value to this input field
                                     */
                                    if (image) {
                                        $.fn.schoolboxResource_Media_Render(fields.url.getValue(), dialog, false);
                                    }
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
                                            fields.url.setValue('/storage/image.php?hash=' + responseText.hash);

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
                    }
                ]
            }
        ]
    }
});

