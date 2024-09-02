(function ($) {
    /**
     * Inline textbox editor constructor.
     *
     * @param {jQuery}  element
     * @param {jQuery}  editorInstance
     * @param {jQuery}  modifyButton
     * @param {jQuery}  saveButton
     * @param {jQuery}  deleteButton
     * @param {number}  fileBrowserCategoryId
     * @constructor
     */
    var InlineTextboxEditor = function (
        element,
        editorInstance,
        modifyButton,
        saveButton,
        deleteButton,
        fileBrowserCategoryId,
    ) {
        var self = this;
        self.states = {
            clean: "clean",
            cleanModify: "cleanModify",
            dirty: "dirty",
            dirtyModify: "dirtyModify",
            saving: "saving",
            deleting: "deleting",
        };
        self.currentState = self.states.clean;
        self.element = element;
        self.editorInstance = editorInstance;
        self.modifyButton = modifyButton;
        self.saveButton = saveButton;
        self.deleteButton = deleteButton;
        self.fileBrowserCategoryId = fileBrowserCategoryId;
        self.editor = null;

        // Set up events
        self.modifyButton.on("click.inlineTextboxEditor", function (event) {
            event.preventDefault;
            self.onClickModify();
        });
        self.saveButton.on("click.inlineTextboxEditor", function (event) {
            event.preventDefault;
            self.onClickSave();
        });
        self.deleteButton.on("click.inlineTextboxEditor", function (event) {
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
        button.attr("data-text", button.text()).prop("disabled", true).addClass("disabled");
        if (isProcessing) {
            button.text(button.attr("data-processing-text"));
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
        var dataText = button.attr("data-text");
        if (dataText === undefined) {
            button.attr("data-text", button.text());
        }
        button.text(dataText).prop("disabled", false).removeClass("disabled");
    };

    /**
     * Switches from the current state to another, and handles all
     * resultant button states.
     *
     * @param {String} newState One of the values in `states`
     */
    InlineTextboxEditor.prototype.switchState = function (newState) {
        // Show/hide and add/remove processing from buttons dependent on state

        /* The Save button should never be visible at the same time as Modify;
         *
         * You're either already modifying it, at which point you only need Save
         * Or, you haven't started modifying it, and you need the Modify button
         * but not the Save button.
         *
         * The Delete button is always available so you can delete a textbox at
         * any time during the editing process
         */
        switch (newState) {
            /* The textbox renders in its initial state
             * You haven't touched or tainted it yet, it's just on screen
             * You can Modify or Delete, but you can't save anything (nothing to save)
             */
            case this.states.clean:
                this.modifyButton.removeClass("hide");
                this.removeProcessing(this.saveButton.addClass("hide"));
                this.removeProcessing(this.deleteButton.removeClass("hide"));
                break;

            /* You've clicked the Modify button
             * You can save or delete, but you can't Modify again
             */
            case this.states.cleanModify:
                this.modifyButton.addClass("hide");
                this.removeProcessing(this.saveButton.removeClass("hide"));
                this.removeProcessing(this.deleteButton.removeClass("hide"));
                break;

            /* When you blur the editor, this executes. You should only have Save and Delete
             * But this doesn't know if you changed the content; it always fires upon losing
             * focus in the editor, so it's not really -dirty- as such. You can click in it
             * and lose focus without having changed anything, and this will still fire
             */
            case this.states.dirty:
                this.modifyButton.addClass("hide");
                this.removeProcessing(this.saveButton.removeClass("hide"));
                this.removeProcessing(this.deleteButton.removeClass("hide"));
                break;

            /* You've changed some content in the textbox, but you haven't saved it yet
             * This executes while you're typing, too, which seems extreme
             */
            case this.states.dirtyModify:
                this.modifyButton.addClass("hide");
                this.removeProcessing(this.saveButton.removeClass("hide"));
                this.removeProcessing(this.deleteButton.removeClass("hide"));
                break;

            /* When you finally hit the Save button
             */
            case this.states.saving:
                this.modifyButton.addClass("hide");
                this.addProcessing(this.saveButton.removeClass("hide"), true);
                this.addProcessing(this.deleteButton.removeClass("hide"), false);
                break;

            /* When you hit the Delete button
             */
            case this.states.deleting:
                this.modifyButton.addClass("hide");
                this.addProcessing(this.saveButton.removeClass("hide"), false);
                this.addProcessing(this.deleteButton.removeClass("hide"), true);
                break;

            default:
                break;
        }
        this.currentState = newState;
    };

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
        var self = this,
            editorInstanceId = self.editorInstance.attr("id");

        /* When you click the Modify button, the ckeditor spawns
         */
        self.element.trigger("inlineTextboxEditor.preEditorCreate", [self]);

        // wait for the editor to be created
        document.getElementById(editorInstanceId).addEventListener("EDITOR__CREATED", () => {
            self.editor = window.sbProps.editors[editorInstanceId];

            // Focus on the editor, then set the cursor to the end of the content
            self.editor.focus();
            self.editor.model.change((writer) => {
                writer.setSelection(writer.createPositionAt(self.editor.model.document.getRoot(), "end"));
            });

            // Scroll the editor into view, specifically, use the action buttons as the anchor point and bring them to the centre of the page if possible.
            // To prevent odd scrolling behaviour, we have to wait a small amount of time for the editor to be rendered fully before we can scroll the view.
            setTimeout(() => {
                self.element
                    .find(".component-action")[0]
                    .scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });
            }, 50);

            /* If you change the content, you're dirty; show the little Changes Not Saved
             * thing by entering dirtyModify state
             */
            self.editor.model.document.on("change:data", () => {
                self.switchState(self.states.dirtyModify);
                self.element.trigger("inlineTextboxEditor.change", [self]);
            });

            /* The Modify button should never be available initially, once you've already
             * clicked it to enter the Modify workflow
             */
            self.switchState(self.states.cleanModify);

            self.element.trigger("inlineTextboxEditor.editorCreate", [self]);
        });

        document.dispatchEvent(
            new CustomEvent("EDITOR__CREATE", {
                detail: {
                    maxCharCount: window.sbProps.MAX_BYTES_MYSQL_MEDIUMTEXT,
                    targetElements: ["#" + editorInstanceId],
                    fileBrowserCategoryId: self.fileBrowserCategoryId,
                },
            }),
        );
    };

    InlineTextboxEditor.prototype.onClickSave = function () {
        var self = this,
            previousState = self.currentState;
        self.switchState(self.states.saving);
        self.element.trigger("inlineTextboxEditor.preSave", [self]);
        $.ajax(self.saveButton.attr("data-form-action"), {
            method: self.saveButton.attr("data-form-method"),
            data: JSON.stringify({
                heading: self.editorInstance.attr("data-textbox-heading"),
                body: self.editor.getData(),
            }),
            dataType: "json",
            contentType: "application/json",
        })
            .done(function () {
                // Editor value is now persisted: the current state is now clean
                self.editor.destroy();
                self.editorInstance.removeAttr("contentEditable");
                self.editorInstance.html(`<div class="loader"></div>`);

                const textboxId = self.editorInstance.attr("data-textbox-id");

                // Pull new content from /textbox/id
                // We grab the new content from the server, as the server may have modified the content in some way.
                // for example, the content will be run through the HTML purifier and iframely, which may change the content.
                $.ajax(`/textbox/${textboxId}`, {
                    method: "GET",
                })
                    .done(function (data) {
                        const parser = new DOMParser();
                        const textBoxDoc = parser.parseFromString(data, "text/html");
                        self.editorInstance.html(textBoxDoc.querySelector(".content article").innerHTML);

                        self.switchState(self.states.clean);
                        self.element.trigger("inlineTextboxEditor.editorDestroy", [self]);
                        self.element.trigger("inlineTextboxEditor.save", [self]);
                    })
                    .fail(function () {
                        self.editorInstance.parent().html(`
                        <div class="alert-box alert">
                            Sorry, we are currently unable to load the text box content.
                            <a href="#${self.editorInstance.attr(
                                "id",
                            )}" onclick="location.reload()">Click here to refresh the page</a>
                            to try again and contact your support if the problem persists.
                        </div>`);
                    });

                // When the editor is destroyed and replaced with html content, the browser can scroll the view to a random point in the page.
                // This could be quite confusing for the user, the best solution to this is to bring the action buttons back into view after the editor is destroyed.
                // As the user just interacted with one of these buttons, they should be able to follow the flow of the page.
                // We must wait a small amount of time for the editor to be destroyed and new content rendered before we can scroll the view.
                setTimeout(() => {
                    self.element
                        .find(".component-action")[0]
                        .scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });
                }, 50);
            })
            .fail(function () {
                self.switchState(previousState);
                self.element.trigger("inlineTextboxEditor.saveFail", [self]);
            });
    };

    InlineTextboxEditor.prototype.onClickDelete = function () {
        var self = this;
        self.deleteButton.confirmDialog({
            text: "Are you sure you want to delete this content permanently?",
            confirmLabel: "Delete",
            confirmEvent: function () {
                var previousState = self.currentState;
                self.switchState(self.states.deleting);
                self.element.trigger("inlineTextboxEditor.preDelete", [self]);
                $.ajax(self.deleteButton.attr("data-form-action"), {
                    method: self.deleteButton.attr("data-form-method"),
                })
                    .done(function () {
                        self.element.trigger("inlineTextboxEditor.delete", [self]);
                        // Don't need to switch state, as reloading the component resets the state
                    })
                    .fail(function () {
                        self.switchState(previousState);
                        self.element.trigger("inlineTextboxEditor.deleteFail", [self]);
                    });
            },
        });
    };

    /**
     * Inline textbox editor plugin.
     *
     * @param options
     */
    $.fn.inlineTextboxEditor = function (options) {
        var defaults = {
            modifyButton: '[data-action="modify"]',
            deleteButton: '[data-action="delete"]',
            saveButton: '[data-action="save"]',
            content: "[data-content]",
        };
        var derivedOptions = $.extend(true, {}, defaults, options);
        this.each(function () {
            var $this = $(this),
                $modifyButton = $this.find(derivedOptions.modifyButton),
                $deleteButton = $this.find(derivedOptions.deleteButton),
                $saveButton = $this.find(derivedOptions.saveButton),
                $editorInstance = $this.find(derivedOptions.editorInstance),
                fileBrowserCategoryId = derivedOptions.fileBrowserCategoryId,
                inlineTextboxEditor = new InlineTextboxEditor(
                    $this,
                    $editorInstance,
                    $modifyButton,
                    $saveButton,
                    $deleteButton,
                    fileBrowserCategoryId,
                );
            $this.data("inlineTextboxEditor", inlineTextboxEditor);
        });
        return this;
    };
})(jQuery);
