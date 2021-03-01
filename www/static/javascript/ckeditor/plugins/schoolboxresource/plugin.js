/* You can find some docs here:
* http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_1
*/
CKEDITOR.plugins.add('schoolboxresource', {
    init: function(editorInstance) {
        editorInstance.addCommand('schoolboxresource', new CKEDITOR.dialogCommand('schoolboxresourceDialog'));

        editorInstance.ui.addButton('SchoolboxResourceMedia', {
            label: 'Embed Video/Audio',
            command: 'schoolboxresource',
            toolbar: 'schoolbox,4'
        });

        editorInstance.ui.addButton('SchoolboxResourceImage', {
            label: 'Embed Image',
            command: 'schoolboxresource',
            toolbar: 'schoolbox,5'
        });

        CKEDITOR.dialog.add('schoolboxresourceDialog', this.path + 'dialogs/schoolboxresource.js');

        var $participantCustomiseModal = $('#participant-customise-modal');
        var $ckeDialogBgCover = $('.cke_dialog_background_cover');

        editorInstance.on('dialogShow', function (event) {
            $participantCustomiseModal.css('z-index', '1001');
            $ckeDialogBgCover.removeClass('hide');
        });

        editorInstance.on('dialogHide', function (event) {
            $participantCustomiseModal.css('z-index', '10000');
            $ckeDialogBgCover.addClass('hide');
        });
    }
});
