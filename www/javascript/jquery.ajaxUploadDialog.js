/**
 * Ajax uploader
 */
(function($) {

    // methods: init, use
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

            // Set up the uploadify script
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
                this.data('ajaxUpload').open(param);
                break;

            case 'init':
            default:
                this.data('ajaxUpload', new AjaxUploadDialog(this, param));
                break;
        }
        return this;
    }
})(jQuery);
