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
            $j('.mark-input').blur(function(){
                $j(this).closest('.inlineform').submit();
            });
            $j('.comment').change(function(){
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

                            var notification = submitDiv.find('input, select, textarea');

                            $j.post('/submission/returnWorkForm2.php', submitDiv.find('input, select, textarea').serialize(), function(data) {

                                // Why don't I have a class for this?
                                notification.addClass('ajax-updated');
                                setTimeout(function() {
                                    notification.removeClass('ajax-updated');
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
                        if(!isNumber($j(this).val())) {
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

/* Move all this extra random stuff away, so I don't have to
 * copy and paste it every time
 */
$(function() {

//	// Add the dark class only to even rows that are visible (skip over the hidden ones)
//	$j('tbody tr:visible:even').addClass('dark');
//
//	// Add another, darker class to th
//	$j('tbody tr:hidden').addClass('sub-table-row');
//
//	$j('tr.submission').each(function () {
//		if ($j(this).next().hasClass('expandable') && !$j(this).hasClass('expandable')) {
//			$j(this).addClass('drop-parent').children('td:first-child').prepend('<a href="#" class="submission-dropdown" title="Show submissions">&#9660;</a>');
//		}
//	});
//
//	$j(document).on('click', '.submission-dropdown', function () {
//		var currentUnicode = $j(this).html().charCodeAt(0);
//
//		if (currentUnicode == '9660') {
//			$j(this).html('&#9650;')
//		}
//		else {
//			$j(this).html('&#9660;')
//		}
//
//		var matchingClasses = $j(this).parents('tr').attr('class').toString().match(/user_(\d+)/);
//		var expandables = '.' + matchingClasses[0] + '.expandable';
//
//		// Clear queue and complete animation
//		$j(this).parents('tbody').find(expandables).stop(true, true).fadeToggle();
//
//		return false;
//	});
//
//	$j('.submission td span.alert').qtip({
//		content:"Submission received after mark given",
//		style: {
//			classes: 'qtip-light qtip-shadow qtip-rounded'
//		}
//	});
});