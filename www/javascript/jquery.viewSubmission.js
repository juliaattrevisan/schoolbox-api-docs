$j(function() {
    var ajax = true;
    var markSheet =  {
        cellValidation: function() {
            $j('.mark-input, .comment').blur(function(){
                if ($j(this).val() !== '') {

                    console.log($j(this).closest('.inlineform'));

                    $j(this).closest('.inlineform').submit();
                }
            });

            /*
            * Depends on the presence of the jquery.validate library.
            * */
            jQuery.validator.addMethod("percentage", function(value, element) {
                return this.optional(element) || (parseFloat(value) <= 100);
            }, "Please enter a value out of 100");

            jQuery.validator.addMethod("markLimit", function(value, element) {

                var markLimit = parseInt($j(element).siblings('span').children('.validation-limit').text());

                if (value === '') {
                    return true;
                } else {
                    return parseInt(value) <= markLimit;
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
        }
    };

    markSheet.cellValidation();
});