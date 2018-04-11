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
            recaptcha: {
                required: true
            }
        },
        messages: {
            name: 'Please enter your name.',
            email: 'Please enter a valid email address.',
            message: 'Please enter a message.',
            recaptcha: 'Please complete the check above so that we know you\'re not a bot.'
        }
    });
});