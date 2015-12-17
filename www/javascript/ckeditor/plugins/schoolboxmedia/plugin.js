/* You can find some docs here:
* http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_1
*/
CKEDITOR.plugins.add('schoolboxmedia', {
    icons: 'schoolboxmedia',
    init: function(editorInstance) {
        editorInstance.addCommand('schoolboxmedia', new CKEDITOR.dialogCommand('schoolboxmediaDialog'));

        editorInstance.ui.addButton('SchoolboxMedia', {
            label: 'Schoolbox Media',
            command: 'schoolboxmedia',
            toolbar: 'schoolbox'
        });

        CKEDITOR.dialog.add('schoolboxmediaDialog', this.path + 'dialogs/schoolboxmedia.js');
    }
});