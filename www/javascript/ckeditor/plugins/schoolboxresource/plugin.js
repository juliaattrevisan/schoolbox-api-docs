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

        editorInstance.on('afterCommandExec', function (event) {
            if ($('#participant-customise-modal').length) {
                $(document).on('click', '.cke_dialog_ui_button_cancel, .cke_dialog_close_button', function (event) {
                    $('#participant-customise-modal').css('z-index', '10000');
                });

                if (event.editor.name === 'plan-desc' && event.data.name === 'schoolboxresource') {
                    $('#participant-customise-modal').css('z-index', '1000');
                } else {
                    $('.cke_dialog_background_cover').css('display', 'block').removeClass('hide');
                }
            }
        });
    }
});
