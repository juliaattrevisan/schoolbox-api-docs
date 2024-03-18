/**
 * The following demonstrates the core functionality of using Firebase Messaging in a web app.
 */
import { initializeApp } from "firebase/app";
import { MessagePayload, deleteToken, getMessaging, getToken, onMessage } from "firebase/messaging";
import { firebaseConfig, vapidKey, serverKey } from "./firebaseDemoConfig";

initializeApp(firebaseConfig); // Initialize Firebase

const messaging = getMessaging(); // Retrieve Firebase Messaging object.
let token = ""; // The current device token

const requestPermissionButton = document.getElementById("request-permission-button") as HTMLElement; // The button to request permission
const testMessageButton = document.getElementById("test-message-button") as HTMLElement; // The button to send a test message

/**
 * Mock server check, just using localStorage instead for now
 * @returns {boolean} - true if the token is in localStorage
 */
function isTokenSentToServer() {
    return window.localStorage.getItem("sentToServer") === "1";
}

/**
 * Mock server check, just using localStorage instead for now
 * @param {boolean} sent - true if the token is in localStorage
 */
function setTokenSentToServer(sent: boolean) {
    window.localStorage.setItem("sentToServer", sent ? "1" : "0");
}

/**
 * Just a place holder function, with the assumption that something
 * like this will be used to store the token on the server.
 * For now we are just storing in the token in local storage
 * @param {string} currentToken - The token to be stored on the server.
 */
function sendTokenToServer(currentToken: string) {
    token = currentToken;
    if (!isTokenSentToServer()) {
        console.warn("Sending token to server...", currentToken);
        // TODO(developer): Send the current token to your server.
        setTokenSentToServer(true);
    } else {
        console.warn("Token already sent to server so won't send it again unless it changes. Token: ", currentToken);
    }
}

/**
 * Get the device token so we can send messages to it
 * if we don't have it already
 */
function getDeviceToken() {
    getToken(messaging, { vapidKey })
        .then((currentToken) => {
            if (currentToken) {
                sendTokenToServer(currentToken);
                // updateUIForPushEnabled(currentToken);
                requestPermissionButton.style.display = "none"; // Hide the button if already have permission
            } else {
                // Show permission request.
                console.log("No registration token available. Request permission to generate one.");
                requestPermissionButton.style.display = "block"; // show the button if we don't have permission
                setTokenSentToServer(false);
            }
        })
        .catch((err) => {
            console.warn("An error occurred while retrieving token. ", err);
            // showToken("Error retrieving registration token.");
            setTokenSentToServer(false);
        });
}

/**
 * To request permission to receive notifications.
 */
function requestPermission() {
    console.warn("Requesting permission...");
    Notification.requestPermission()
        .then((permission) => {
            if (permission === "granted") {
                console.log("Notification permission granted.");
                // TODO(developer): Retrieve a registration token for use with FCM.
                // In many cases once an app has been granted notification permission,
                // it should update its UI reflecting this.
                getDeviceToken();
            } else {
                console.warn("Unable to get permission to notify.");
            }
        })
        .catch((err) => {
            console.warn("An error occurred while retrieving token. ", err);
        });
}
requestPermissionButton.addEventListener("click", requestPermission);

/**
 * Send a message to the device using the FCM server
 */
function sendTestMessage() {
    const notification = {
        title: "Portugal vs. Denmark",
        body: "5 to 1",
        icon: "firebase-logo.png",
        click_action: "http://localhost:8081",
    };
    fetch("https://fcm.googleapis.com/fcm/send", {
        method: "POST",
        headers: {
            Authorization: `key=${serverKey}`,
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            notification,
            to: token,
        }),
    })
        .then((response) => {
            console.warn(response);
        })
        .catch((error) => {
            console.warn(error);
        });
}
testMessageButton.addEventListener("click", sendTestMessage);

/**
 * Add a message to the messages element.
 * @param {MessagePayload} payload - The message payload to add to the UI.
 */
function appendMessage(payload: MessagePayload) {
    const messagesElement = document.querySelector("#messages")!;
    const dataHeaderElement = document.createElement("h5");
    const dataElement = document.createElement("pre");
    dataHeaderElement.textContent = "Received message:";
    dataElement.textContent = JSON.stringify(payload, null, 2);
    messagesElement.appendChild(dataHeaderElement);
    messagesElement.appendChild(dataElement);
}

/**
 * Handle incoming messages. Called when:
 * - a message is received while the app has focus
 * - the user clicks on an app notification created by a service worker
 *   `messaging.onBackgroundMessage` handler.
 */
onMessage(messaging, (payload) => {
    appendMessage(payload);
});

// to send a test message, we'll need the device token
getDeviceToken();
