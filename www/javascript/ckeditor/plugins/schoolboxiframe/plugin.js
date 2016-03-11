/* You can find some docs here:
* http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_1
*/
CKEDITOR.plugins.add('schoolboxiframe', {
    init: function(editorInstance) {
        editorInstance.addCommand('schoolboxiframe', new CKEDITOR.dialogCommand('schoolboxiframeDialog'));

        editorInstance.ui.addButton('schoolboxIframe', {
            label: 'Embed Iframe',
            command: 'schoolboxiframe',
            toolbar: 'schoolbox,6'
        });

        CKEDITOR.dialog.add('schoolboxiframeDialog', this.path + 'dialogs/schoolboxiframe.js');
    }
});