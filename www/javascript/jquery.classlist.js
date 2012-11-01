// set validator to accept hidden fields
jQuery.validator.setDefaults({
    debug: true,
    ignore: []
});

// set up validation
$j(document).ready(function() {
    $j("#contact-table").validate({
        rules: {
            bcc: "required"
        },
        messages: {
            bcc: "No students selected."
        },
        // by all accounts, this shouldn't need to be defined, and yet the form won't submit by default
        submitHandler: function(form) {
            form.submit();
        }
    });
});

// set up checkbox parsing
$j(function () {
    $j('input[type=checkbox]').change(function (e) {
        $j('#bcc').val('');
        $j('input[type=checkbox]:checked').each(function (e) {
            $j('#bcc').val($j('#bcc').val() + $j(this).val() + ', ');
        });
    });
    $j('#emailall').click(function (e) {
        $j('#bcc').val('');
        $j('input[type=checkbox]').each(function (e) {
            $j('#bcc').val($j('#bcc').val() + $j(this).val() + ', ');
        });
    });
});