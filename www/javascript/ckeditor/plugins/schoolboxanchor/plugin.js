/* You can find some docs here:
* http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_1
*/
CKEDITOR.plugins.add('schoolboxanchor', {
    init: function(editorInstance) {
        editorInstance.addCommand('schoolboxanchor', new CKEDITOR.dialogCommand('schoolboxanchorDialog'));

        editorInstance.ui.addButton('schoolboxAnchor', {
            label: 'Anchor',
            command: 'schoolboxanchor',
            toolbar: 'schoolbox,3'
        });

        CKEDITOR.dialog.add('schoolboxanchorDialog', this.path + 'dialogs/schoolboxanchor.js');
    }
});