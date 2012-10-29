function isNumber(n) {
    return !isNaN(parseFloat(n)) && isFinite(n);
}

$j(function() {
    var ajax = true;
    var markSheet =  {
        cellValidation: function() {
            $j('.mark-input, .comment').blur(function(){
                if ($j(this).val() !== '') {
                    var inlinefrm = $j(this).closest('.inlineform');
                    inlinefrm.submit();
                }
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
                    return parseInt(value) <= markLimit;
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
                        if(ajax) {
                            // perform ajax update of work, no file upload
                            var notification = $j(form).closest('.inlineform').find('input, select');
                            $j.post('/submission/returnWorkForm.php', $j(form).serialize(), function(data) {
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

    markSheet.cellValidation();
    markSheet.selectGrouping();
});