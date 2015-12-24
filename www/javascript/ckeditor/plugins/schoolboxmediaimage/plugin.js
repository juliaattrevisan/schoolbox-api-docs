/* You can find some docs here:
* http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_1
*/
CKEDITOR.plugins.add('schoolboxmediaimage', {
    icons: 'schoolboxmediaimage',
    init: function(editorInstance) {
        editorInstance.addCommand('schoolboxmediaimage', new CKEDITOR.dialogCommand('schoolboxmediaImageDialog'));

        editorInstance.ui.addButton('SchoolboxMediaImage', {
            label: 'Schoolbox Media (Image)',
            command: 'schoolboxmediaimage',
            toolbar: 'schoolbox'
        });

        CKEDITOR.dialog.add('schoolboxmediaImageDialog', this.path + 'dialogs/schoolboxmediaimage.js');
    }
});