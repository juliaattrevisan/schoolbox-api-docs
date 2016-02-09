CKEDITOR.dialog.add('schoolboxlinkDialog', function (editorInstance) {

    var config = {
        url: {
            /* Find information about a node/resource
             */
            nodeinfo: '/editor/nodeInfo.php'
        }
    };

    /**
     * Inject link into editor panel with modifiers applied
     *
     * @param editorInstance
     * @param fields
     * @param url
     * @returns {*}
     */
    function inject(editorInstance, fields, url) {
        var element = editorInstance.document.createElement('iframe');
        element.setAttribute('src', url);

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
                                },

                                /* When you type the url yourself, apply details
                                 */
                                onChange: function() {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);
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

                                    /* Select something from the Resource Browser
                                     */
                                    $.fn.schoolboxResource();

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
                                                    fields.type.setValue(responseText.type);
                                                    fields.filesystem.setValue(responseText.filesystem);

                                                    /* Shut modal, go back to Dialog
                                                     */
                                                    $('[data-reveal]').foundation('reveal', 'close');
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
                        className: 'xhide'
                    },

                    /* Store string 'image', 'video', 'audio'
                     * Used by various switch statements for rendering data
                     */
                    {
                        type: 'text',
                        id: 'schoolboxlink-type',
                        label: 'Type',
                        className: 'xhide'
                    },

                    /* The filesystem where the resource was found
                     */
                    {
                        type: 'text',
                        id: 'schoolboxlink-filesystem',
                        label: 'Filesystem',
                        className: 'xhide'
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

            editorInstance.insertElement(inject(editorInstance, fields, fields.url.getValue()));
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

            if (!this.insertMode) {
                this.setupContent(this.element);
            }
        }
    };
});
