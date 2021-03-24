CKEDITOR.dialog.add('schoolboxiframeDialog', function (editorInstance) {
    /**
     * Ignore non-numeric key presses
     * Allows (9) Tab character for field switching
     * Allows (8) Backspace character for modification
     * Allows (46) Delete character for modification
     * Allows (37) Left Arrow for navigation
     * Allows (39) Right Arrow for navigation
     *
     * @param event
     */
    function disableNonNumeric(event) {
        var allowedControlKeys = [8, 9, 46, 37, 39];

        /* Allow control keys
         */
        if (allowedControlKeys.indexOf(event.data.$.keyCode) > -1) {
            return;
        }
        /* Disallow non-numerics
         */
        else if (!numericOnly(event.data.$.keyCode)) {
            event.data.preventDefault();
        }
    }

    /**
     * The keyCode pressed was a number
     *
     * @param keyCode
     * @returns {boolean}
     */
    function numericOnly(keyCode) {
        return ([48,49,50,51,52,53,54,55,56,57,96,97,98,99,100,101,102,103,104,105].indexOf(keyCode) > -1);
    }

    /**
     * Inject image into editor panel with modifiers applied
     *
     * @param editorInstance
     * @param fields
     * @param url
     * @returns {*}
     */
    function inject(editorInstance, fields, url) {
        var styles = [];
        var classes = [];

        var element = editorInstance.document.createElement('iframe');
        element.setAttribute('src', url);
        element.setAttribute('allowfullscreen', '');

        /* Determine width - if fullwidth checked, use 100%
         * otherwise use specified values. Note that width:100%
         * is not allowed, so use the usual class instead
         *
         * Include both styles and classes to display this; because
         * of htmlpurifier and ckeditor iframe respectively
         */
        if (fields.fullwidth.getValue()) {
            classes.push('full-width');
            styles.push('width:100%');
        }
        else {
            if (fields.width.getValue()) {
                styles.push('width:' + fields.width.getValue() + 'px');
            }
        }

        /* Height is always applied if set
         */
        if (fields.height.getValue()) {
            styles.push('height:' + fields.height.getValue() + 'px');
        }

        /* Alignment
         * Floats left and right;
         * centered uses 0 auto;
         * none is no floating, just display inline
         */
        if (fields.alignment != undefined) {
            switch (fields.alignment.getValue()) {

                /* Center aligned, no text wrapping
                 */
                case 'center':
                    styles.push('margin:0 auto');
                    styles.push('display:block');
                    break;

                /* Left aligned, text wraps against right edge
                 */
                case 'left':
                    styles.push('float:' + fields.alignment.getValue());

                    /* Horizontal Spacing (applies margin-left and margin-right)
                     * Ignore margin-left when left aligned
                     */
                    if (fields.horizontalMargin.getValue() != '') {
                        styles.push('margin-right:' + fields.horizontalMargin.getValue() + 'px');
                    }

                    break;

                /* Right aligned, text wraps against left edge
                 */
                case 'right':
                    styles.push('float:' + fields.alignment.getValue());

                    /* Horizontal Spacing (applies margin-left and margin-right)
                     * Ignore margin-right when right aligned
                     */
                    if (fields.horizontalMargin.getValue() != '') {
                        styles.push('margin-left:' + fields.horizontalMargin.getValue() + 'px');
                    }

                    break;

                /* Left aligned, no text wrapping
                 */
                default:

                    /* Horizontal Spacing (applies margin-left and margin-right)
                     */
                    if (fields.horizontalMargin.getValue() != '') {
                        styles.push('margin-left:' + fields.horizontalMargin.getValue() + 'px');
                        styles.push('margin-right:' + fields.horizontalMargin.getValue() + 'px');
                    }

                    break;
            }
        }

        /* Vertical Spacing (applies margin-top and margin-bottom)
         */
        if (fields.verticalMargin.getValue() != '') {
            styles.push('margin-top:' + fields.verticalMargin.getValue() + 'px');
            styles.push('margin-bottom:' + fields.verticalMargin.getValue() + 'px');
        }

        /* Border
         */
        if (fields.border.getValue() != '') {
            styles.push('border:1px solid #808080;');
        }

        /* Combine and inject
         */
        element.setAttribute('class', classes.join(' '));
        element.setAttribute('style', styles.join(';'));

        return element;
    }

    /**
     * A macro to all the fields in the Dialog
     * (because it's such a longwinded way to retrieve them)
     *
     * @param dialog
     * @returns {{url: *, width: *, height: *, aspectRatio: *, aspectRatioValue: *, fullwidth: *, preview: (*|Mixed|jQuery|HTMLElement)}}
     */
    function dialogFields(dialog) {
        return {
            url: dialog.getContentElement('schoolboxiframe-tab', 'schoolboxiframe-url'),
            width: dialog.getContentElement('schoolboxiframe-tab', 'schoolboxiframe-width'),
            height: dialog.getContentElement('schoolboxiframe-tab', 'schoolboxiframe-height'),
            fullwidth: dialog.getContentElement('schoolboxiframe-tab', 'schoolboxiframe-fullwidth'),
            alignment: dialog.getContentElement('schoolboxiframe-tab', 'schoolboxiframe-alignment'),
            border: dialog.getContentElement('schoolboxiframe-tab', 'schoolboxiframe-border'),
            verticalMargin: dialog.getContentElement('schoolboxiframe-tab', 'schoolboxiframe-verticalmargin'),
            horizontalMargin: dialog.getContentElement('schoolboxiframe-tab', 'schoolboxiframe-horizontalmargin')
        };
    }

    /**
     * Extract a particular css property's value from a style string
     *
     * @param property
     * @param style
     */
     function extractStyle(property, style) {
        if (style != null) {
            var allProperties = style.split(';');

            for (var i = 0; i < allProperties.length; i++) {
                var current = allProperties[i].split(':');

                /* Replace any spaces in the property name before checking
                 * as sometimes the style attribute is formatted
                 */
                if (current[0].replace(' ', '') == property) {
                    return current[1];
                }
            }
        }

        return '';
    }

    return {
        title: 'Embed IFrame',
        minWidth: 400,
        minHeight: 200,

        /**
         * Tab Contents
         */
        contents: [
            {
                id: 'schoolboxiframe-tab',
                label: 'Embed IFrame',
                elements: [
                    {
                        type: 'text',
                        id: 'schoolboxiframe-url',
                        label: 'Url',
                        validate: CKEDITOR.dialog.validate.notEmpty( "Url cannot be empty." ),

                        /* Editing an existing element
                         */
                        setup: function(element) {
                            var dialog = this.getDialog();
                            var fields = dialogFields(dialog);

                            fields.url.setValue(element.getAttribute('src'));
                        },

                        /* When you type the url yourself, apply details
                         */
                        onChange: function() {
                            var dialog = this.getDialog();
                            var fields = dialogFields(dialog);

                            var url = fields.url.getValue();

                            /* If you pasted an iframe embed into the url field
                             * regex out the source url, width and height params
                             */
                            var regex = /<iframe\ width="(\d+)"\ height="(\d+)" src="([^"]+)"/g;
                            var matches = regex.exec(url);

                            if ((matches != null) && (matches.length)) {
                                fields.width.setValue(matches[1]);
                                fields.height.setValue(matches[2]);
                                fields.url.setValue(matches[3]);
                            }
                            /* Try reformatting non-iframeable url
                             */
                            else {
                                var regex = /watch\?v\=/;
                                var matches = regex.exec(url);

                                if ((matches != null) && matches.length) {
                                    url = url.replace('watch?v=', '/v/');

                                    fields.url.setValue(url);
                                }
                            }
                        }
                    },
                    {
                        type: 'html',
                        html: '<label class="schoolboxresource-separator">Dimensions</label>'
                    },
                    {
                        type: 'hbox',
                        widths: ['50%', '50%'],
                        children: [
                            {
                                type: 'text',
                                id: 'schoolboxiframe-width',
                                label: 'Width',

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    /* Determine if Fullwidth should remain ticked
                                     */
                                    if (extractStyle('width', element.getAttribute('style')) != '100%') {
                                        var width = extractStyle('width', element.getAttribute('style')).replace('px', '');

                                        fields.width.setValue(width);
                                    }
                                },

                                /* Disable non-numeric characters
                                 */
                                onKeyDown: function(event) {
                                    disableNonNumeric(event);
                                }
                            },
                            {
                                type: 'text',
                                id: 'schoolboxiframe-height',
                                label: 'Height',
                                'default': '500',

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    var height = extractStyle('height', element.getAttribute('style')).replace('px', '');

                                    fields.height.setValue(height);
                                },

                                /* Disable non-numeric characters
                                 */
                                onKeyDown: function(event) {
                                    disableNonNumeric(event);
                                }
                            }
                        ]
                    },
                    {
                        type: 'checkbox',
                        id: 'schoolboxiframe-fullwidth',
                        label: 'Fullwidth',
                        'default': true,

                        /* Editing an existing element
                         */
                        setup: function(element) {
                            var dialog = this.getDialog();
                            var fields = dialogFields(dialog);

                            /* Determine if Fullwidth should remain ticked
                             */
                            if (element.getAttribute('class') == 'full-width') {
                                fields.fullwidth.setValue(true);
                            }
                            else {
                                fields.fullwidth.setValue(false);
                            }
                        }
                    },
                    {
                        type: 'html',
                        html: '<label class="schoolboxresource-separator">Appearance</label>'
                    },
                    {
                        type: 'hbox',
                        widths: ['33%', '33%', '33%'],
                        children: [
                            {
                                type: 'select',
                                id: 'schoolboxiframe-alignment',
                                label: 'Alignment',
                                items: [['None', 'none'], ['Left', 'left'], ['Center', 'center'], ['Right', 'right']],
                                'default': 'none',

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    var float = extractStyle('float', element.getAttribute('style'));
                                    var margin = extractStyle('margin', element.getAttribute('style'));

                                    if (float == 'left' || float == 'right') {
                                        fields.alignment.setValue(float);
                                    }
                                    else if (margin == '0 auto') {
                                        fields.alignment.setValue('center');
                                    }
                                    else {
                                        fields.alignment.setValue('none');
                                    }
                                }
                            },
                            {
                                type: 'text',
                                id: 'schoolboxiframe-verticalmargin',
                                label: 'Vertical Spacing',

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    var margin = extractStyle('margin-top', element.getAttribute('style')).replace('px', '');

                                    if (margin.length) {
                                        fields.verticalMargin.setValue(margin);
                                    }
                                },

                                /* Disable non-numeric characters
                                 */
                                onKeyDown: function(event) {
                                    disableNonNumeric(event);
                                }
                            },
                            {
                                type: 'text',
                                id: 'schoolboxiframe-horizontalmargin',
                                label: 'Horizontal Spacing',

                                /* Editing an existing element
                                 */
                                setup: function(element) {
                                    var dialog = this.getDialog();
                                    var fields = dialogFields(dialog);

                                    var marginLeft = extractStyle('margin-left', element.getAttribute('style')).replace('px', '');
                                    var marginRight = extractStyle('margin-right', element.getAttribute('style')).replace('px', '');

                                    if (marginLeft.length) {
                                        fields.horizontalMargin.setValue(marginLeft);
                                    }
                                    else if (marginRight.length) {
                                        fields.horizontalMargin.setValue(marginRight);
                                    }
                                },

                                /* Disable non-numeric characters
                                 */
                                onKeyDown: function(event) {
                                    disableNonNumeric(event);
                                }
                            }
                        ]
                    },
                    {
                        type: 'checkbox',
                        id: 'schoolboxiframe-border',
                        label: 'Border',

                        /* Editing an existing element
                         */
                        setup: function(element) {
                            var dialog = this.getDialog();
                            var fields = dialogFields(dialog);

                            if (extractStyle('border', element.getAttribute('style')).length) {
                                fields.border.setValue(true);
                            }
                        }
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

            if (!element || element.getName() != 'iframe') {
                element = editorInstance.document.createElement('iframe');
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
