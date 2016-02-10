/* You can find some docs here:
* http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_1
*/
CKEDITOR.plugins.add('schoolboxresource', {
    icons: 'schoolboxresource',
    init: function(editorInstance) {
        editorInstance.addCommand('schoolboxresource', new CKEDITOR.dialogCommand('schoolboxresourceDialog'));

        editorInstance.ui.addButton('SchoolboxResource', {
            label: 'Embed Resource',
            command: 'schoolboxresource',
            toolbar: 'schoolbox,3'
        });

        CKEDITOR.dialog.add('schoolboxresourceDialog', this.path + 'dialogs/schoolboxresource.js');
    }
});