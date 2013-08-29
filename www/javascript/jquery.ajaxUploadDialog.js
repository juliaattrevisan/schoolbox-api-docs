/**
 * Ajax uploader for files. Uses the Uploadify method.
 *
 * Usage:
 * Apply this plugin to an element to use as a dialog.
 *
 * HTML:
 * <div class="file_uploader hide">
 * <form action="/storage/asyncUpload.php" method="post" enctype="multipart/form-data">
 * <input id="upload" name="upload" type="file" />
 * <input type="submit" value="Upload" class="submit-btn-plain" />
 * </form>
 * </div>
 *
 * JS:
 * $(".file_uploader").ajaxUploadDialog({session: {name: 'PHPSESSID', id: '1234...'}});
 * $(".file_uploader").ajaxUploadDialog('open', function(output) {
 *     ... do something with the return from the form's action here
 * });
 */
(function($) {

    /**
     * Creates the AJAX upload dialog.
     *
     * @param element The jQuery element to apply the dialog to.
     * @param options Any options to set on the dialog.
     *        session.id:       the ID of the current session
     *        session.name:     the name of the current session
     *        dialogOptions:    pass through to the dialog constructor
     *        uploadifyOptions: pass through to uploadify constructor
     */
    var AjaxUploadDialog = function(element, options) {
        var self      = this;
        self.element  = element;
        self.settings = {};
        self.uploadCallback = function(output) {};
        var defaults  = {
            session: {
                id:   '',
                name: ''
            },
            dialogOptions: {
                minWidth: 400,
                modal:    true,
                autoOpen: false
            },
            uploadifyOptions: {
                uploader:         '/javascript/uploadify/uploadify.swf',
                script:           '/storage/asyncUpload.php',
                cancelImg:        '/javascript/uploadify/cancel.png',
                fileDataName:     'upload',
                multi:            false,
                auto:             false,
                descriptionField: false,
                removeCompleted:  true,
                onComplete:       function(event, code, file, output) {
                    self.uploadCallback(output);
                    self.element.dialog('close');
                }
            }
        };
        self.settings = $.extend(true, {}, defaults, options);

        // Create the dialog
        self.element.dialog(self.settings.dialogOptions);
        self.element.find('form').each(function() {
            var form               = $(this);
            var uploadify_settings = self.settings.uploadifyOptions;

            // Build script for submission
            var script = form.attr('action');
            var query  = form.serialize();
            if (self.settings.session.id) {
                query += (query.length ? '&' : '');
                query += self.settings.session.name + '=' + self.settings.session.id;
            }
            uploadify_settings.script = (script + '?' + query);

            // Set up the uploadifyer
            var file_upload = form.find('input[type="file"]').first();
            if (file_upload.length) {
                file_upload.uploadify(uploadify_settings);

                // Form submit event - trigger the file upload instead of
                // submitting the form.
                form.submit(function(event) {
                    event.preventDefault();
                    file_upload.uploadifyUpload();
                });
            }
        });
    }

    /**
     * Opens the AJAX upload dialog.
     *
     * @param uploadCallback callback to call when the file is uploaded.
     *        Is passed the result from the dialog's form.
     */
    AjaxUploadDialog.prototype.open = function(uploadCallback) {
        var self = this;
        self.uploadCallback = uploadCallback;
        self.element.dialog('open');
    }

    $.fn.ajaxUploadDialog = function(method, param) {
        // method could be: init or open
        if (typeof method !== 'string') {
            param  = method;
            method = 'init';
        }
        switch (method) {
            case 'open':
                this.data('ajaxUploadDialog').open(param);
                break;

            case 'init':
            default:
                this.data('ajaxUploadDialog', new AjaxUploadDialog(this, param));
                break;
        }
        return this;
    }
})(jQuery);
