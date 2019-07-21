$(function() {
    $('#feedbackForm').alaressValidate({
        rules: {
            email: {
                email: true
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