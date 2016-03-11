/* You can find some docs here:
* http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_1
*/
CKEDITOR.plugins.add('schoolboxlink', {
    init: function(editorInstance) {
        editorInstance.addCommand('schoolboxlink', new CKEDITOR.dialogCommand('schoolboxlinkDialog'));

        editorInstance.ui.addButton('schoolboxLink', {
            label: 'Link',
            command: 'schoolboxlink',
            toolbar: 'schoolbox,0'
        });

        editorInstance.ui.addButton('Unlink', {
            label: 'Unlink',
            command: 'unlink',
            toolbar: 'schoolbox,1'
        });

        editorInstance.addCommand('unlink', new CKEDITOR.unlinkCommand());

        CKEDITOR.dialog.add('schoolboxlinkDialog', this.path + 'dialogs/schoolboxlink.js');
    }
});

/* Pinched from random plugin with neither regret nor remorse
 */
CKEDITOR.unlinkCommand = function () {
};

CKEDITOR.unlinkCommand.prototype = {
    exec: function (editor) {
        var style = new CKEDITOR.style({
            element: 'a',
            type: CKEDITOR.STYLE_INLINE,
            alwaysRemoveElement: 1
        });

        editor.removeStyle(style);
    },

    refresh: function (editor, path) {
        // Despite our initial hope, document.queryCommandEnabled() does not work
        // for this in Firefox. So we must detect the state by element paths.

        var element = path.lastElement && path.lastElement.getAscendant('a', true);

        if (element && element.getName() == 'a' && element.getAttribute('href') && element.getChildCount()) {
            this.setState(CKEDITOR.TRISTATE_OFF);
        }
        else {
            this.setState(CKEDITOR.TRISTATE_DISABLED);
        }
    },

    contextSensitive: 1,
    startDisabled: 1,
    requiredContent: 'a[href]'
};