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

                                /* Asynchronously find out more information
                                 * Inject appropriate url into dialog
                                 */
                                var textInput = self.getDialog().getContentElement('schoolboxmedia-image-tab', 'schoolboxmedia-image-url');

                                /* The url from which to request more info
                                 */
                                var url = '/editor/nodeInfo.php';

                                /* Find out how to render this file
                                 */
                                $.getJSON(url, { hash: message.data[0].hash }, function(responseText) {
                                    console.log(responseText);
                                });

//                                var response = $.parseJSON(responseText);
//
//
//
//
//                                content = '/storage/image.php?hash='+response.hash;
//
//                                textInput.setValue(content);
//                            });


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
