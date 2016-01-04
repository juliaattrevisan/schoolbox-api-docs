/* You can find some docs here:
 * http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_1
 */
CKEDITOR.plugins.add('schoolboxresourcemedia', {
    icons: 'schoolboxresourcemedia',
    init: function(editorInstance) {
        editorInstance.addCommand('schoolboxresourcemedia', new CKEDITOR.dialogCommand('schoolboxresourceMediaDialog'));

        editorInstance.ui.addButton('SchoolboxResourceMedia', {
            label: 'Schoolbox Resource (Audio/Video Media)',
            command: 'schoolboxresourcemedia',
            toolbar: 'schoolbox'
        });

        CKEDITOR.dialog.add('schoolboxresourceMediaDialog', this.path + 'dialogs/schoolboxresourcemedia.js');
    }
});