function isNumber(n) {
    return !isNaN(parseFloat(n)) && isFinite(n);
}

$j(function() {
    var ajax = true;
    var markSheet =  {
        makeValidatable: function() {
            // the validator doesn't seem to be able to handle two inputs with the same name, so they need to be
            // uniqu-ified, then ajax submitted
            var inputs = $j('#returnWorkForm').find('input.mark-input');
            inputs.each(function() {
                var self = $j(this);
                var pInline = self.closest('.inlineform');
                var uid = pInline.find('input.uid');
                self.attr('name', self.attr('name').substring(0, self.attr('name').length - 2) + '[' + uid.val() + ']');
            });
        },

        cellValidation: function() {
            $j('.mark-input, .comment').change(function(){
                $j(this).closest('.inlineform').submit();
            });

            /*
            * Depends on the presence of the jquery.validate library.
            * */
            jQuery.validator.addMethod("percentage", function(value, element) {
                if(jQuery.combobox.instances.length) {
                    var valid = jQuery.combobox.instances[0].selector.allSelectOptions;
                    if(jQuery.inArray(value, valid) != -1) {
                        return true;
                    }
                }
                return this.optional(element) || (parseFloat(value) <= 100);
            }, "Please enter a value out of 100");

            jQuery.validator.addMethod("markLimit", function(value, element) {
                if(jQuery.combobox.instances.length) {
                    var valid = jQuery.combobox.instances[0].selector.allSelectOptions;
                    if(jQuery.inArray(value, valid) != -1) {
                        return true;
                    }
                }
                var markLimit = parseInt($j(element).closest('.inlineform').find('.validation-limit').text());
                if (value !== "") {
                    var v = parseInt(value);
                    return v <= markLimit && v >= 0;
                } else {
                    return true;
                }
            }, "Amount must be less or equal to mark limit");


            jQuery.validator.addClassRules({
                percent: {
                    percentage: true
                },

                'out-of': {
                    markLimit: true
                }
            });

            $j('#returnWorkForm').each(function() {
                $j(this).validate({
                    submitHandler: function(form) {
                        // Any inline submit forms? (there will be, if we are
                        // focused on a form element.) Use them if so.
                        // (Thankfully, the change event triggers before blur
                        //  in all browsers.)
                        if ($j(form).find('div.inline-submit').length) {
                            // perform ajax update of work, no file upload
                            var submitDiv = $j(form).find('div.inline-submit');
                            var submitForm = submitDiv.clone();

                            // if there's a select, align the values
                            submitForm.find('select').val(submitDiv.find('select').val());

                            var notification = submitDiv.find('input, select');

                            // remove all the [] from all the elements
                            submitForm.find('input, select').each(function() {
                                var self = $j(this);
                                self.attr('name', self.attr('name').replace(/\[[0-9]*\]/,''));
                            });

                            $j.post('/submission/returnWorkForm.php', submitForm.find('input, select').serialize(), function(data) {
                                notification.css({
                                    'border-color': '#080'
                                });
                                setTimeout(function() {
                                    notification.css({
                                        'border-color': '#ccc'
                                    });
                                }, 1000)
                            });
                            return false;
                        }
                        else {
                            // perform standard form submit, will encapsulate file uploads. set the mark update fields to false
                            $j('.mark-update').each(function() {
                                $j(this).val('false');
                            });
                            form.submit();
                        }
                    }
                });
            });

            $j('#upload-returns-submit').click(function() {
                ajax = false;
            });

            // Focus/blur events on all return work form inputs: focus sets this
            // row as the inline submit form, blur unsets it.
            var inputs = $j('#returnWorkForm :input');
            inputs.focus(function() {
                $j(this).closest('.inlineform').addClass('inline-submit');
            })
            inputs.blur(function() {
                $j(this).closest('.inlineform').removeClass('inline-submit');
            })
        },

        selectGrouping: function() {
            $j('#content select.mark-input').each(function() {
                // if the select doesn't contain a separator for the error codes, slot it in
                var self = $j(this);
                if (self.find('optgroup').length == 0) {
                    // find the first error (non-numeric) option
                    self.children('option').each(function() {
                        if(!isNumber($j(this).attr('value'))) {
                            // insert before me
                            $j(this).before('<optgroup></optgroup>');
                            // break the loop
                            return false;
                        }
                    });
                }
            });
        }
    };

    markSheet.makeValidatable();
    markSheet.cellValidation();
    markSheet.selectGrouping();
});