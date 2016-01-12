/* You can find some docs here:
* http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_1
*/
CKEDITOR.plugins.add('schoolboxiframe', {
    icons: 'schoolboxiframe',
    init: function(editorInstance) {
        editorInstance.addCommand('schoolboxiframe', new CKEDITOR.dialogCommand('schoolboxiframeDialog'));

        editorInstance.ui.addButton('schoolboxIframe', {
            label: 'Schoolbox IFrame',
            command: 'schoolboxiframe',
            toolbar: 'schoolbox'
        });

        CKEDITOR.dialog.add('schoolboxiframeDialog', this.path + 'dialogs/schoolboxiframe.js');
    }
});