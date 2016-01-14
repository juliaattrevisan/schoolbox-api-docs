/* You can find some docs here:
* http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_1
*/
CKEDITOR.plugins.add('schoolboxresource', {
    icons: 'schoolboxresource',
    init: function(editorInstance) {
        editorInstance.addCommand('schoolboxresource', new CKEDITOR.dialogCommand('schoolboxresourceDialog'));

        editorInstance.ui.addButton('SchoolboxResource', {
            label: 'Schoolbox Resource',
            command: 'schoolboxresource',
            toolbar: 'schoolbox,0'
        });

        CKEDITOR.dialog.add('schoolboxresourceDialog', this.path + 'dialogs/schoolboxresource.js');
    }
});