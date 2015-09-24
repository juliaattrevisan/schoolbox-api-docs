/**
 * jQuery warning length plugin
 */
(function($) {
    "use strict";

    var WarningLength = function(element, options) {
        var defaults = {
            bindTo: ['change', 'keypress'],
            warningMessage: "You are reaching the maximum length (${current}/${max} characters)",
            maxMessage: "You have reached the maximum length (${max} characters)",
            warningLength: element.data('warninglength'),
            maxLength: element.attr('maxlength'),
            messagePrototype: $('<div />'),
            messagePlacement: function(message, element) {
                element.after(message);
            }
        };
        var self = this;
        self.element = element;
        self.options = $.extend({}, defaults, options);
        self.options.bindTo.forEach(function(trigger) {
            self.element.on(trigger+'.warninglength', self.onChange.bind(self));
        });
        self.currentMessage = $();
    };

    /**
     * On change event - triggers hide or show of message.
     */
    WarningLength.prototype.onChange = function() {
        this.showMessage(this.getMessage(this.element.val().length))
    };

    /**
     * Gets whatever the appropriate message is.
     *
     * @param currentLength
     * @returns String empty if no message is to be shown,
     *                 this.options.(warningMessage|maxMessage) with
     *                 ${current} and ${max} replaced with current and max
     *                 length otherwise.
     */
    WarningLength.prototype.getMessage = function(currentLength) {
        var message = '';
        if (currentLength >= this.options.maxLength) {
            message = this.options.maxMessage;
        } else if (currentLength >= this.options.warningLength) {
            message = this.options.warningMessage;
        }
        return message
            .replace('${current}', currentLength)
            .replace('${max}', this.options.maxLength)
        ;
    };

    /**
     * Replaces the current warning message.
     *
     * @param message
     */
    WarningLength.prototype.showMessage = function(message) {
        this.currentMessage.remove();
        if (message.length) {
            this.currentMessage = this.options.messagePrototype.clone().text(message);
            if (this.currentMessage.is('label') && this.element.is('[id]')) {
                // attach label messages to the element
                this.currentMessage.attr('for', this.element.attr('id'));
            }
            this.options.messagePlacement(this.currentMessage, this.element);
        }
    };

    $.fn.warninglength = function(options) {
        this.each(function() {
            var element = $(this);
            element.data('warninglength_plugin', new WarningLength(element, options));
        });
        return this;
    };
})(jQuery);
