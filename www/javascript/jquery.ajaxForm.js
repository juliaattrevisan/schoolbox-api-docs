/**
 * AJAX form plugin.
 *
 * Options: same as $.ajax with following defaults:
 * * url: from form's action
 * * type: from form's method
 * * dataType: JSON
 *
 * Events:
 * * triggers ajaxForm.done on successful request
 * * triggers ajaxForm.fail on unsuccessful request
 * Both events take the same parameters as their jqXHR equivalents
 *
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
(function($){
    var AjaxForm = function(form, options) {
        var self     = this;
        var defaults = {
            url:      form.attr('action'),
            type:     form.attr('method'),
            dataType: 'json'
        }
        self.options = $.extend({}, defaults, options)
        self.form    = form
        self.form.on('submit.ajaxForm', function(event) {
            event.preventDefault();
            var params = self.options;
            params.data = self.form.serializeArray()
            $.ajax(params)
            .done(function(data, textStatus, jqXHR) {
                self.form.trigger('ajaxForm.done', [data, textStatus, jqXHR])
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                self.form.trigger('ajaxForm.fail', [jqXHR, textStatus, errorThrown])
            })
        })
    }

    $.fn.ajaxForm = function(options) {
        this.data('ajaxForm', new AjaxForm(this, options))
        return this
    }
})(jQuery);
