CKEDITOR.dialog.add('schoolboxanchorDialog', function (editorInstance) {

    /**
     * Inject link into editor panel with modifiers applied
     *
     * @param editorInstance
     * @param selection
     * @param fields
     * @returns {*}
     */
    function inject(editorInstance, selection, fields) {
        var element = editorInstance.document.createElement('a');
        element.setAttribute('id', fields.id.getValue());

        /* No selection.

         * TODO: Hmm I'm not sure when this occurs, as an empty
         * selection (or, placement of caret) is equivalent to
         * CKEDITOR.SELECTION_TEXT anyway...
         */
        if (selection.getType() == CKEDITOR.SELECTION_NONE) {
            element.setText(fields.id.getValue());
        }
        /* Applying link to selected text; use the selected text
         * as the link text itself. Or, if nothing was selected,
         * just insert a new string with the url as link text
         */
        else if (selection.getType() == CKEDITOR.SELECTION_TEXT) {
            if (selection.getSelectedText().length) {
                element.setText(selection.getSelectedText());
            }
            else {
                element.setText(fields.id.getValue());
            }
        }
        /* Applying link to some kind of element like an img
         * Use the element as children of element
         */
        else if (selection.getType() == CKEDITOR.SELECTION_ELEMENT) {
            element.append(selection.getSelectedElement());
        }

        return element;
    }

    /**
     * A macro to all the fields in the Dialog
     * (because it's such a longwinded way to retrieve them)
     *
     * @param dialog
     * @returns {{url: *}}
     */
    function dialogFields(dialog) {
        return {
            id: dialog.getContentElement('schoolboxanchor-tab', 'schoolboxanchor-id')
        };
    }

    return {
        title: 'Anchor',
        minWidth: 400,
        minHeight: 200,

        /**
         * Tab Contents
         */
        contents: [
            {
                id: 'schoolboxanchor-tab',
                label: 'Link',
                elements: [
                    {
                        type: 'hbox',
                        widths: ['80%', '20%'],
                        children: [
                            {
                                type: 'text',
                                id: 'schoolboxanchor-id',
                                label: 'Id (Anchor)',
                                validate: CKEDITOR.dialog.validate.notEmpty( "Anchor cannot be empty." ),

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    fields.id.setValue(element.getAttribute('id'));
                                }
                            }
                        ]
                    }
                ]
            }
        ],

        /**
         * Manufacture html and insert into editor
         */
        onOk: function() {
            var dialog = this;
            var fields = dialogFields(dialog);

            editorInstance.insertElement(inject(editorInstance, this.selection, fields));
        },

        /**
         * Dialog startup
         *
         * Occurs when you click the Schoolbox Media button regardless
         * of what you have currently selected
         */
        onShow: function() {

            /* Deal with existing/inserting new elements
             */
            var selection = editorInstance.getSelection();
            var element = selection.getStartElement();

            if (!element || element.getName() != 'a') {
                element = editorInstance.document.createElement('a');
                this.insertMode = true;
            }
            else {
                this.insertMode = false;
            }

            this.element = element;
            this.selection = selection;

            if (!this.insertMode) {
                this.setupContent(this.element);
            }
        }
    };
});
