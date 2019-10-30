CKEDITOR.dialog.add('schoolboxlinkDialog', function (editorInstance) {

    var config = {
        url: {
            /* Find information about a node/resource
             */
            nodeinfo: '/editor/nodeInfo.php',

            /* Prefix for linking to Folders
             */
            folder: '/homepage/',

            /* Prefix for linking to MyFolders
             */
            myfolder: '/my-files/',

            /* Prefix for link to Storage hash
             */
            storage: '/storage/fetch.php?hash=',

            /* Prefix for linking to Files
             */
            file: '/send.php?id='
        }
    };

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
        element.setAttribute('href', fields.url.getValue());

        if (fields.target.getValue() == '_blank') {
            element.setAttribute('target', fields.target.getValue());
        }

        /* No selection.

         * TODO: Hmm I'm not sure when this occurs, as an empty
         * selection (or, placement of caret) is equivalent to
         * CKEDITOR.SELECTION_TEXT anyway...
         */
        if (selection.getType() == CKEDITOR.SELECTION_NONE) {
            element.setText(fields.url.getValue());
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
                element.setText(fields.url.getValue());
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
            id: dialog.getContentElement('schoolboxlink-tab', 'schoolboxlink-id'),
            type: dialog.getContentElement('schoolboxlink-tab', 'schoolboxlink-type'),
            url: dialog.getContentElement('schoolboxlink-tab', 'schoolboxlink-url'),
            target: dialog.getContentElement('schoolboxlink-tab', 'schoolboxlink-target'),
            filesystem: dialog.getContentElement('schoolboxlink-tab', 'schoolboxlink-filesystem')
        };
    }

    return {
        title: 'Link',
        minWidth: 400,
        minHeight: 200,

        /**
         * Tab Contents
         */
        contents: [
            {
                id: 'schoolboxlink-tab',
                label: 'Link',
                elements: [
                    {
                        type: 'hbox',
                        widths: ['80%', '20%'],
                        children: [
                            {
                                type: 'text',
                                id: 'schoolboxlink-url',
                                label: 'Url',
                                validate: CKEDITOR.dialog.validate.notEmpty( "Url cannot be empty." ),

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    fields.url.setValue(element.getAttribute('href'));
                                }
                            },
                            {
                                type: 'button',
                                id: 'schoolboxlink-browse',
                                label: 'Browse',
                                className: 'schoolboxresource-button',
                                onClick: function() {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    var domId = $(this).attr('domId');
                                    var previousModal = $('#'+domId).closest('.cke_dialog');
                                    previousModal.addClass('hide');

                                    /* Select something from the Resource Browser
                                     */
                                    $.fn.schoolboxResource({
                                        allowedFilesystems: 'internal',
                                        previousModal: previousModal
                                    });

                                    $(window).unbind('message').on('message', function(event) {
                                        var message;

                                        try {
                                            message = JSON.parse(event.originalEvent.data);
                                        } catch (err) {
                                            message = event.originalEvent.data;
                                        }

                                        /* Find out how to render this file
                                         */
                                        if (message.data != undefined) {
                                            if (0 in message.data && 'hash' in message.data[0]) {
                                                $.getJSON(config.url.nodeinfo, {identifier: message.data[0].hash}, function (responseText) {
                                                    fields.id.setValue(responseText.id);
                                                    fields.type.setValue(responseText.type);
                                                    fields.filesystem.setValue(responseText.filesystem);

                                                    /* Manufacture link url based on whether it was
                                                     * a file or a folder
                                                     *
                                                     * Assign link text to be same as url for now
                                                     * User can change it to whatever they want
                                                     */
                                                    if (fields.type.getValue() == 'folder') {
                                                        fields.url.setValue(config.url.folder + fields.id.getValue());
                                                    }
                                                    else if (fields.type.getValue() == 'myfolder') {
                                                        fields.url.setValue(config.url.myfolder + fields.id.getValue());
                                                    }
                                                    else if (fields.filesystem.getValue() == 'store') {
                                                        fields.url.setValue(config.url.storage + fields.id.getValue());
                                                    }
                                                    else {
                                                        fields.url.setValue(config.url.file + fields.id.getValue());
                                                    }

                                                    /* Shut modal, go back to Dialog
                                                     */
                                                    $('#file-picker').foundation('reveal', 'close');
                                                    $('#participant-customise-modal').css('z-index', '10000');
                                                });
                                            } else {
                                                // The file was rejected, probably cause it was the wrong mimetype.
                                                // Do nothing
                                            }
                                        }
                                        else {

                                            /* Connecting to your Google Drive
                                             */
                                        }
                                    });
                                }
                            }
                        ]
                    },
                    {
                        type: 'select',
                        id: 'schoolboxlink-target',
                        label: 'Opens in',
                        items: [['Same window', '_self'], ['New window', '_blank']],
                        'default': '',

                        /* Editing an existing element
                         */
                        setup: function(element) {
                            var dialog = this.getDialog();
                            var fields = dialogFields(dialog);

                            fields.target.setValue(element.getAttribute('target'));
                        }
                    },

                    /* Metadata lives at the bottom because they occupy space
                     * due to containing td element having padding
                     */

                    /* Store the file id here, because audio/video renders
                     * with a numeric id, not a hash, and has multiple
                     * urls for previews and content stream itself
                     *
                     * Not used for images
                     */
                    {
                        type: 'text',
                        id: 'schoolboxlink-id',
                        label: 'Id',
                        className: 'hide'
                    },

                    /* Store string 'image', 'video', 'audio'
                     * Used by various switch statements for rendering data
                     */
                    {
                        type: 'text',
                        id: 'schoolboxlink-type',
                        label: 'Type',
                        className: 'hide'
                    },

                    /* The filesystem where the resource was found
                     */
                    {
                        type: 'text',
                        id: 'schoolboxlink-filesystem',
                        label: 'Filesystem',
                        className: 'hide'
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
            $('#participant-customise-modal').css('z-index', '10000');
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
