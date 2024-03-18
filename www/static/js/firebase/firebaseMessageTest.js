/**
 * old legacy method
 */
const key =
    "AAAAzmovUO0:APA91bGe0pQsYrAymeU3FSppWeMuAU7cDI3te2VqQYI_hn5JUt48n1fccB1012ShkdBZr2nXOaL0BePr1eZn0qIGuNvnAoT4yoidc9rBZUyk0ewW0xUk-jLEvQWAcoC-feB9400BaW2k";
const to =
    "dd5gaA6KxrHNqnXFS-6iml:APA91bEmh2N2oKJuXOLmBGwvM_DiH3K3xxrp91nj5TD82ZREbtTC1wCKOR1jSz1jxgEH4L1hH31GGVIhyGfYs0QIpry71VQZhTcYT_n8EkubtrSuTX25Lyr3sxC1ovhCpxFzvBLrjtiQ";
const notification = {
    title: "Portugal vs. Denmark",
    body: "5 to 1",
    icon: "firebase-logo.png",
    click_action: "http://localhost:8081",
};

fetch("https://fcm.googleapis.com/fcm/send", {
    method: "POST",
    headers: {
        Authorization: `key=${key}`,
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        notification: notification,
        to: to,
    }),
})
    .then(function (response) {
        console.log(response);
    })
    .catch(function (error) {
        console.error(error);
    });
