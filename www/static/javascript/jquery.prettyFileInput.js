/*******

 jQuery prettyFileInput
 Author: Tom Gordon
 Company: Alaress
 Release: 31/05/2011

 "if ya needs thar a prettier file inputstick tharn come on doooown"

 This changes file inputs to be prettier!
 I started with this basic converter

 http://www.viget.com/inspire/custom-file-inputs-with-a-bit-of-jquery/

 so thanks for that!

 v. 0.1

 + inputHolderClass: this wraps all the appended elements and the file input itself.
 + buttonClass: this is the class for the 'button', the thing underneath the invisible file input. how sneaky!
 + buttonActiveClass: for when a file has been selected.
 + additionalButtonClasses: if you need extra classes on the button whack em in here.
 + fakeFileHolderClass: this is the class for the element that holds the filename like "chimpney.jpg"
 + defaultText: This is the text on the button
 + defaultFileSelectedText: This is the text that the button transforms to.

 Known bug: if this is applied to an input within a <label> tag, the open file
            dialog triggers twice. I haven't found a way around this.
            This doesn't happen to elements with an external <label>.

 *******/

(function($) {
    "use strict";
    $.fn.prettyFileInput = function(options) {
        var defaults = {
            inputHolderClass: 'file-input',
            buttonClass: 'btn',
            additionalButtonClasses: 'btn-file-input',
            buttonActiveClass: 'btn-file-input-active',
            fakeFileHolderClass: 'file-holder',
            defaultText: 'Choose file',
            defaultFileSelectedText: 'File selected',
            callback: false

        };
        options = $.extend({}, defaults, options);

        return this.each(function() {

            var obj = $(this);

            obj.wrap(
                $('<label />')
                    .addClass(options.inputHolderClass)
            );
            obj.after(
                $('<span />')
                    .addClass(options.buttonClass + ' ' + options.additionalButtonClasses)
                    .html(options.defaultText)
            );

            // Shrink the file input to nothingness without actually hiding it,
            // so that click events can still be triggered.
            obj.width(0).height(0);

            // Change event for files
            obj.bind('change.prettyFileInput', function() {

                var $val = obj.val();

                var valArray = $val.split('\\');
                var newVal = valArray[valArray.length - 1];
                var $button = obj.siblings('.' + options.buttonClass.replace(' ', '.') + '');
                var $fakeHolder = obj.siblings('.' + options.fakeFileHolderClass + '');

                if (newVal !== '') {
                    $button.addClass(options.buttonActiveClass).html(options.defaultFileSelectedText);
                }

                if ($fakeHolder.length === 0) {
                    obj.parent().append(
                        $('<span />')
                            .addClass(options.fakeFileHolderClass)
                            .text(newVal)
                    )
                } else {
                    $fakeHolder.text(newVal);
                }

                if (($fakeHolder.length > 0) && (newVal === '')) {
                    $fakeHolder.remove();
                    $button.html(options.defaultText).removeClass().addClass(options.buttonClass + ' ' + options.additionalButtonClasses);
                }

                if(options.callback) {
                    var f = options.callback;
                    f(this);
                }

            });
        });
    };
})(jQuery);