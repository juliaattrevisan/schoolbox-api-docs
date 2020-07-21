(function($) {

    /**
     * Inline textbox editor constructor.
     *
     * @param {jQuery} modifyButton
     * @param {jQuery} saveButton
     * @param {jQuery} deleteButton
     * @constructor
     */
    var InlineTextboxEditor = function (modifyButton, saveButton, deleteButton) {
        this.states = {
            clean:       'clean',
            cleanModify: 'cleanModify',
            dirty:       'dirty',
            dirtyModify: 'dirtyModify',
            saving:      'saving',
            deleting:    'deleting',
        };
        this.currentState = states.clean;
        this.modifyButton = modifyButton;
        this.saveButton   = saveButton;
        this.deleteButton = deleteButton;
        this.editor       = null;
    };

    /**
     * Add the processing styles from this button, and optionally set
     * its text to its processing value.
     *
     * @param {jQuery}  button
     * @param {Boolean} isProcessing
     */
    InlineTextboxEditor.prototype.addProcessing = function (button, isProcessing) {
        // Store previous button text, update its text
        button.attr('data-text', button.text())
              .prop('disabled', true)
              .addClass('disabled');
        if (isProcessing) {
            button.text(button.attr('data-processing-text'));
        }
    };


    /**
     * Remove the processing styles from this button, and set its text
     * back to its default value.
     *
     * @param {jQuery} button
     */
    InlineTextboxEditor.prototype.removeProcessing = function (button) {
        // Reset button text back to its default
        var dataText = button.attr('data-text');
        if (dataText === undefined) {
            button.attr('data-text', button.text());
        }
        button.text(dataText)
              .prop('disabled', false)
              .removeClass('disabled');
    };


    /**
     * Switches from the current state to another, and handles all
     * resultant button states.
     *
     * @param {String} newState One of the values in `states`
     */
    InlineTextboxEditor.prototype.switchState = function (newState) {
        // Add/remove processing from buttons dependent on state
        switch (newState) {
            case this.states.clean:
            case this.states.cleanModify:
            case this.states.dirty:
            case this.states.dirtyModify:
                this.removeProcessing(this.saveButton);
                this.removeProcessing(this.deleteButton);
                break;

            case this.states.saving:
                this.addProcessing(this.saveButton, true);
                this.addProcessing(this.deleteButton, false);
                break;

            case this.states.deleting:
                this.addProcessing(this.saveButton, false);
                this.addProcessing(this.deleteButton, true);
                break;

            default:
                break;
        }

        // Show/hide buttons dependent on state
        switch (newState) {
            case this.states.clean:
                this.modifyButton.removeClass('hide');
                this.saveButton.addClass('hide');
                this.deleteButton.removeClass('hide');
                break;

            case this.states.cleanModify:
                this.modifyButton.addClass('hide');
                this.saveButton.removeClass('hide');
                this.deleteButton.removeClass('hide');
                break;

            case this.states.dirty:
                this.modifyButton.removeClass('hide');
                this.saveButton.removeClass('hide');
                this.deleteButton.removeClass('hide');
                break;

            case this.states.dirtyModify:
                this.modifyButton.addClass('hide');
                this.saveButton.removeClass('hide');
                this.deleteButton.removeClass('hide');
                break;

            case this.states.saving:
                this.modifyButton.addClass('hide');
                this.saveButton.removeClass('hide');
                this.deleteButton.removeClass('hide');
                break;

            case this.states.deleting:
                this.modifyButton.addClass('hide');
                this.saveButton.removeClass('hide');
                this.deleteButton.removeClass('hide');
                break;

            default:
                break;
        }
        this.currentState = newState;
    };


    $.fn.inlineTextboxEditor = function () {
    };
})(jQuery);
