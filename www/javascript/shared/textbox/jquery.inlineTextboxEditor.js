(function($) {
    /**
     * Inline textbox editor constructor.
     *
     * @param {jQuery}  element
     * @param {jQuery}  editorInstance
     * @param {jQuery}  modifyButton
     * @param {jQuery}  saveButton
     * @param {jQuery}  deleteButton
     * @param {Object}  editorInstanceOptions
     * @param {Boolean} editorInline
     * @constructor
     */
    var InlineTextboxEditor = function (
        element,
        editorInstance,
        modifyButton,
        saveButton,
        deleteButton,
        editorInstanceOptions,
        editorInline
    ) {
        var self = this;
        self.states = {
            clean:       'clean',
            cleanModify: 'cleanModify',
            dirty:       'dirty',
            dirtyModify: 'dirtyModify',
            saving:      'saving',
            deleting:    'deleting',
        };
        self.currentState          = self.states.clean;
        self.element               = element;
        self.editorInstance        = editorInstance;
        self.modifyButton          = modifyButton;
        self.saveButton            = saveButton;
        self.deleteButton          = deleteButton;
        self.editorInstanceOptions = editorInstanceOptions;
        self.editorInline          = editorInline;
        self.editor                = null;


        // Set up events
        self.modifyButton.on('click.inlineTextboxEditor', function (event) {
            event.preventDefault;
            self.onClickModify();
        });
        self.saveButton.on('click.inlineTextboxEditor', function (event) {
            event.preventDefault;
            self.onClickSave();
        });
        self.deleteButton.on('click.inlineTextboxEditor', function (event) {
            event.preventDefault;
            self.onClickDelete();
        });
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
        // Show/hide and add/remove processing from buttons dependent on state
        switch (newState) {
            case this.states.clean:
                this.modifyButton.removeClass('hide');
                this.removeProcessing(this.saveButton.addClass('hide'));
                this.removeProcessing(this.deleteButton.removeClass('hide'));
                break;

            case this.states.cleanModify:
                this.modifyButton.addClass('hide');
                this.removeProcessing(this.saveButton.removeClass('hide'));
                this.removeProcessing(this.deleteButton.removeClass('hide'));
                break;

            case this.states.dirty:
                this.modifyButton.removeClass('hide');
                this.removeProcessing(this.saveButton.removeClass('hide'));
                this.removeProcessing(this.deleteButton.removeClass('hide'));
                break;

            case this.states.dirtyModify:
                this.modifyButton.addClass('hide');
                this.removeProcessing(this.saveButton.removeClass('hide'));
                this.removeProcessing(this.deleteButton.removeClass('hide'));
                break;

            case this.states.saving:
                this.modifyButton.addClass('hide');
                this.addProcessing(this.saveButton.removeClass('hide'), true);
                this.addProcessing(this.deleteButton.removeClass('hide'), false);
                break;

            case this.states.deleting:
                this.modifyButton.addClass('hide');
                this.addProcessing(this.saveButton.removeClass('hide'), false);
                this.addProcessing(this.deleteButton.removeClass('hide'), true);
                break;

            default:
                break;
        }
        this.currentState = newState;
    }


    /**
     * Is the editor in a dirty state?
     *
     * @returns {boolean}
     */
    InlineTextboxEditor.prototype.isDirty = function () {
        return this.currentState === this.states.dirty || this.currentState === this.states.dirtyModify;
    };


    /**
     * On-click event for when the
     */
    InlineTextboxEditor.prototype.onClickModify = function () {
        var self             = this,
            editorInstanceId = self.editorInstance.attr('id');
        if (window.CKEDITOR.instances[editorInstanceId]) {
            window.CKEDITOR.instances[editorInstanceId].focus();
        } else {
            self.element.trigger('inlineTextboxEditor.preEditorCreate', [self]);
            self.editor = self.editorInline
                ? window.CKEDITOR.inline(editorInstanceId, self.editorInstanceOptions)
                : window.CKEDITOR.replace(editorInstanceId, self.editorInstanceOptions);
            self.editor.on('instanceReady', function (event) {
                // Focus on the editor
                event.editor.setReadOnly(false);
                event.editor.focus();
                self.element.trigger('inlineTextboxEditor.editorCreate', [self]);
            });
            self.editor.on('focus', function () {
                // Switch state of modify button
                self.switchState(self.editor.checkDirty() ? self.states.dirtyModify : self.states.cleanModify);
                self.element.trigger('inlineTextboxEditor.focus', [self]);
            });
            self.editor.on('blur', function () {
                // Revert state of modify button
                self.switchState(self.editor.checkDirty() ? self.states.dirty : self.states.clean);
                self.element.trigger('inlineTextboxEditor.blur', [self]);
            });
            self.editor.on('change', function () {
                // Has the content changed since saved?
                self.switchState(self.editor.checkDirty() ? self.states.dirtyModify : self.states.cleanModify);
                self.element.trigger('inlineTextboxEditor.change', [self]);
            });
        }
    };


    InlineTextboxEditor.prototype.onClickSave = function () {
        var self          = this,
            previousState = self.currentState;
        self.switchState(self.states.saving);
        self.element.trigger('inlineTextboxEditor.preSave', [self]);
        $.ajax(
            self.saveButton.attr('data-form-action'),
            {
                method: self.saveButton.attr('data-form-method'),
                data:   JSON.stringify({
                    heading: self.editorInstance.attr('data-textbox-heading'),
                    body:    self.editor.getData(),
                }),
                dataType:    'json',
                contentType: 'application/json',
            }
        ).done(function () {
            // Editor value is now persisted: the current state is now clean
            self.editor.destroy();
            self.editorInstance.removeAttr('contentEditable');
            self.switchState(self.states.clean);
            self.element.trigger('inlineTextboxEditor.editorDestroy', [self]);
            self.element.trigger('inlineTextboxEditor.save', [self]);
        }).fail(function () {
            self.switchState(previousState);
            self.element.trigger('inlineTextboxEditor.saveFail', [self]);
        });
    };


    InlineTextboxEditor.prototype.onClickDelete = function () {
        var self = this;
        self.deleteButton.confirmDialog({
            text: 'Are you sure you want to delete this content permanently?',
            confirmLabel: 'Delete',
            confirmEvent: function () {
                var previousState = self.currentState;
                self.switchState(self.states.deleting);
                self.element.trigger('inlineTextboxEditor.preDelete', [self]);
                $.ajax(
                    self.deleteButton.attr('data-form-action'),
                    { method: self.deleteButton.attr('data-form-method'), }
                ).done(function () {
                    self.element.trigger('inlineTextboxEditor.delete', [self]);
                    // Don't need to switch state, as reloading the component resets the state
                }).fail(function () {
                    self.switchState(previousState);
                    self.element.trigger('inlineTextboxEditor.deleteFail', [self]);
                });
            }
        });
    };


    /**
     * Inline textbox editor plugin.
     *
     * @param options
     */
    $.fn.inlineTextboxEditor = function (options) {
        var defaults = {
            modifyButton:          '[data-action="modify"]',
            deleteButton:          '[data-action="delete"]',
            saveButton:            '[data-action="save"]',
            content:               '[data-content]',
            editorInstanceOptions: {},
            editorInline:          true,
        };
        var derivedOptions = $.extend(true, {}, defaults, options);
        this.each(function () {
            var $this               = $(this),
                $modifyButton       = $this.find(derivedOptions.modifyButton),
                $deleteButton       = $this.find(derivedOptions.deleteButton),
                $saveButton         = $this.find(derivedOptions.saveButton),
                $editorInstance     = $this.find(derivedOptions.editorInstance),
                inlineTextboxEditor = new InlineTextboxEditor(
                    $this,
                    $editorInstance,
                    $modifyButton,
                    $saveButton,
                    $deleteButton,
                    derivedOptions.editorInstanceOptions,
                    derivedOptions.editorInline
                );
            $this.data('inlineTextboxEditor', inlineTextboxEditor);
        });
        return this;
    };
})(jQuery);
