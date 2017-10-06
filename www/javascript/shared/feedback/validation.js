$(function() {
    $('#feedbackForm').alaressValidate({
        rules: {
            name: {
                required: true
            },
            email: {
                required: true,
                email: true
            },
            message: {
                required: true
            },
            recaptcha_response_field: {
                required: true
            }
        },
        messages: {
            name:  'Please enter your name.',
            email: 'Please enter a valid email address.',
            message: 'Please enter a message.',
            recaptcha_response_field: 'Please enter the two corresponding words for the human checker.'
        }
    });
});