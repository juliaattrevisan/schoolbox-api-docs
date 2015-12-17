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

                            var url = '/test.php?ajax=1';
                            var data = {};

                            var textInput = this.getDialog().getContentElement('schoolboxmedia-image-tab', 'schoolboxmedia-image-url');

                            $.fn.schoolboxMediaResourceBrowser();
//
//                            $.post(url, data, function(responseText) {
//                                var content = '';
//                                var response = $.parseJSON(responseText);
//
//
//
//
//                                content = '/storage/image.php?hash='+response.hash;
//
//                                textInput.setValue(content);
//                            });
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
