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
        element.setAttribute('id', fields.id.getValue().replace('#', ''));

        /* Only use what the user has specified in the dialog
         */
        element.setText(fields.display.getValue());

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
            id: dialog.getContentElement('schoolboxanchor-tab', 'schoolboxanchor-id'),
            display: dialog.getContentElement('schoolboxanchor-tab', 'schoolboxanchor-display')
        };
    }

    /**
     * Allow alphanumeric, hyphen, underscore, colons, period chars
     * Convert all others to underscore
     *
     * @param val
     * @returns string
     */
    function translateToId(val) {
        return '#' + val.replace(/[^0-9a-zA-Z\-_:\.]/g, '_');
    }

    return {
        title: 'Anchor',
        minWidth: 400,
        minHeight: 50,

        /**
         * Tab Contents
         */
        contents: [
            {
                id: 'schoolboxanchor-tab',
                label: 'Link',
                elements: [
                    {
                        type: 'text',
                        id: 'schoolboxanchor-display',
                        label: 'Anchor Text',
                        validate: CKEDITOR.dialog.validate.notEmpty("Anchor cannot be empty."),

                        /* Editing an existing element
                         */
                        setup: function(element, selection) {
                            var dialog = this.getDialog();
                            var fields = dialogFields(dialog);

                            if (selection.getSelectedText().length) {
                                fields.display.setValue(selection.getSelectedText());
                            }
                            else {
                                fields.display.setValue(element.getText());
                            }
                        },

                        /* Update id value upon change
                         * Anchor text can display different to their actual id val
                         */
                        onKeyUp: function() {
                            var dialog = this.getDialog();
                            var fields = dialogFields(dialog);

                            /* No spaces or punctuation
                             */
                            fields.id.setValue(translateToId(fields.display.getValue()));

                            /* Style up help text
                             */
                            $('#schoolboxlink-anchor-example').html(fields.id.getValue());
                        },

                        /* Alter details about existing node
                         */
                        commit: function(element) {
                            var dialog = this.getDialog();
                            var fields = dialogFields(dialog);

                            element.setText(fields.display.getValue());
                        }
                    },

                    {
                        type: 'text',
                        id: 'schoolboxanchor-id',
                        label: 'Anchor Link',

                        onLoad: function() {
                            var dialog = this.getDialog();
                            var fields = dialogFields(dialog);

                            /* This is a readonly field, users should not be able to change this
                             * value at all. If they want to alter the anchor value, they can do
                             * it in the Source view
                             */
                            fields.id.disable();
                        },

                        /* Editing an existing element
                         */
                        setup: function(element, selection) {
                            var dialog = this.getDialog();
                            var fields = dialogFields(dialog);

                            fields.id.setValue(element.getAttribute('id'));
                        },

                        /* Alter details about existing node
                         */
                        commit: function(element) {
                            var dialog = this.getDialog();
                            var fields = dialogFields(dialog);

                            element.setAttribute('id', translateToId(fields.display.getValue()));
                        }
                    },

                    {
                        type: 'html',
                        html: '<p>Add the anchor link to the end of your page URL to create the anchor link URL.<br /><br />E.g. <span class="schoolboxlink-anchor-preamble">/homepage/1234</span><span id="schoolboxlink-anchor-example" class="schoolboxlink-anchor-example"></span></p>'
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

            /* Alter details about existing node
             */
            this.commitContent(this.element);

            /* Or insert a new one if needed
             */
            if (this.insertMode) {
                editorInstance.insertElement(inject(editorInstance, this.selection, fields));
            }
        },

        /**
         * Dialog startup
         *
         * Occurs when you click the Schoolbox Media button regardless
         * of what you have currently selected
         */
        onShow: function() {
            var dialog = this;
            var fields = dialogFields(dialog);

            /* Deal with existing/inserting new elements
             */
            var selection = editorInstance.getSelection();
            var element = selection.getStartElement();

            if (element) {
                element = element.getAscendant('a', true);
            }

            if (!element || element.getName() != 'a') {
                element = editorInstance.document.createElement('a');
                this.insertMode = true;
            }
            else {
                this.insertMode = false;
            }

            this.element = element;
            this.selection = selection;

            this.setupContent(this.element, this.selection);

            if (this.insertMode && selection.getSelectedText().length) {
                fields.id.setValue(translateToId(fields.display.getValue()));

                /* This is a readonly field, users should not be able to change this
                 * value at all. If they want to alter the anchor value, they can do
                 * it in the Source view
                 */
                fields.id.disable();

                /* Style up help text
                 */
                $('#schoolboxlink-anchor-example').html(fields.id.getValue());
            }
        }
    };
});
