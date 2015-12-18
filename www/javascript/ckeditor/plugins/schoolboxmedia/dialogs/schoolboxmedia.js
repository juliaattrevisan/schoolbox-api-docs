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
                        validate: CKEDITOR.dialog.validate.notEmpty( "Url cannot be empty." )
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
                                    $.fn.schoolboxMediaResourceBrowserRenderer(responseText, responseText.type, self.getDialog());

                                    /* Shut modal, go back to Dialog
                                     */
                                    $('[data-schoolboxmedia-reveal]').foundation('reveal', 'close');
                                });
                            });
                        }
                    },
                    {
                        type: 'hbox',
                        widths: ['50%', '50%'],
                        children: [
                            {
                                type: 'text',
                                id: 'schoolboxmedia-image-width',
                                label: 'Width',
                                validate: CKEDITOR.dialog.validate.notEmpty( "Width cannot be empty." )
                            },
                            {
                                type: 'text',
                                id: 'schoolboxmedia-image-height',
                                label: 'Height',
                                validate: CKEDITOR.dialog.validate.notEmpty( "Height cannot be empty." )
                            }
                        ]
                    }
                ]
            }
        ]
    };
});
