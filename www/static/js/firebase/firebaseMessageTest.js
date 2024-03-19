/**
 * Cloud Messaging API (Legacy)
 * This method is deprecated and will be removed in the future.
 * So we should be aiming for the new Firebase Cloud Messaging API (V1)
 */
const serverKey =
    "AAAAzmovUO0:APA91bGe0pQsYrAymeU3FSppWeMuAU7cDI3te2VqQYI_hn5JUt48n1fccB1012ShkdBZr2nXOaL0BePr1eZn0qIGuNvnAoT4yoidc9rBZUyk0ewW0xUk-jLEvQWAcoC-feB9400BaW2k";
const deviceToken =
    "dd5gaA6KxrHNqnXFS-6iml:APA91bHxWdVhACgVAaOzj6XnJNH1S-hJD1CAPoHXNhMf_UfAdwmpxrunUeE2rj2N95ygSBYamUZZfQDdOUn3Z8Ln7DuN-tyDo-jdOd4jcEsXqkH1fOBBj37mDKxUEgwOceF7bj640Xyx";
const notification = {
    title: "Here's a message for you",
    body: "And here's the body of the message",
    icon: "",
    click_action: "https://vagrant.dev.schoolbox.cloud/greenhouse/donny",
};

fetch("https://fcm.googleapis.com/fcm/send", {
    method: "POST",
    headers: {
        Authorization: `key=${serverKey}`,
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        notification: notification,
        to: deviceToken,
    }),
})
    .then(function (response) {
        console.log(response);
    })
    .catch(function (error) {
        console.error(error);
    });

/**
 * Firebase Cloud Messaging API (V1)
 * In order to use this new method, we need to generate an access token.
 * Further details on migrating to the new API can be found here:
 * https://firebase.google.com/docs/cloud-messaging/migrate-v1?hl=en&authuser=0
 * and
 * https://firebase.google.com/docs/cloud-messaging/auth-server?authuser=0#python_1
 * Essentially we'll first need a server to generate the access token prior to sending the message.
 */
fetch("https://fcm.googleapis.com//v1/projects/quickstartjsmessaging/messages:send", {
    method: "POST",
    headers: {
        Authorization: "Bearer " + "<Your access token>",
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        message: {
            token: deviceToken,
            notification: notification,
        },
    }),
})
    .then(function (response) {
        console.log(response);
    })
    .catch(function (error) {
        console.error(error);
    });
