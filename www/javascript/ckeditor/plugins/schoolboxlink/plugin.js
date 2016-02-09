/* You can find some docs here:
* http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_1
*/
CKEDITOR.plugins.add('schoolboxlink', {
    icons: 'schoolboxlink',
    init: function(editorInstance) {
        editorInstance.addCommand('schoolboxlink', new CKEDITOR.dialogCommand('schoolboxlinkDialog'));

        editorInstance.ui.addButton('schoolboxLink', {
            label: 'Link',
            command: 'schoolboxlink',
            toolbar: 'schoolbox,3'
        });

        CKEDITOR.dialog.add('schoolboxlinkDialog', this.path + 'dialogs/schoolboxlink.js');
    }
});