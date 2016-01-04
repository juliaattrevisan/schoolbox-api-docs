/* You can find some docs here:
* http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_1
*/
CKEDITOR.plugins.add('schoolboxresourceimage', {
    icons: 'schoolboxresourceimage',
    init: function(editorInstance) {
        editorInstance.addCommand('schoolboxresourceimage', new CKEDITOR.dialogCommand('schoolboxresourceImageDialog'));

        editorInstance.ui.addButton('SchoolboxResourceImage', {
            label: 'Schoolbox Resource (Image)',
            command: 'schoolboxresourceimage',
            toolbar: 'schoolbox'
        });

        CKEDITOR.dialog.add('schoolboxresourceImageDialog', this.path + 'dialogs/schoolboxresourceimage.js');
    }
});