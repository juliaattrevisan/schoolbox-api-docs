/**
 * The following demonstrates the core functionality of using Firebase Messaging in a web app.
 */

import { timeout } from "@js/util/jsUtils";
import { initializeApp } from "firebase/app";
import { MessagePayload, deleteToken, getMessaging, getToken, onMessage } from "firebase/messaging";
import { firebaseConfig, vapidKey, serverKey } from "./firebaseDemoConfig";

initializeApp(firebaseConfig); // Initialize Firebase

const messaging = getMessaging(); // Retrieve Firebase Messaging object.
let token = ""; // The current device token

const requestPermissionButton = document.getElementById("request-permission-button") as HTMLElement; // The button to request permission
const deleteTokenButton = document.getElementById("delete-token-button") as HTMLElement; // The button to delete a token
const testFrgMessageButton = document.getElementById("test-frg-message-button") as HTMLElement; // The button to send a foreground test message
const testBkgMessageButton = document.getElementById("test-bkg-message-button") as HTMLElement; // The button to send a background test message
const deviceTokenElement = document.getElementById("device-token") as HTMLElement; // The element to display the device token

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
    deviceTokenElement.textContent = currentToken;
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
                deleteTokenButton.style.display = "block"; // show the button as we now have a token
            } else {
                // Show permission request.
                console.warn("No registration token available. Request permission to generate one.");
                requestPermissionButton.style.display = "block"; // show the button if we don't have permission
                deleteTokenButton.style.display = "none"; // hide the button as we no longer have a token
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
                console.warn("Notification permission granted.");
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
 * Delete registration token.
 */
function deleteTokenFromFirebase() {
    getToken(messaging)
        .then((currentToken) => {
            deleteToken(messaging)
                .then(() => {
                    console.warn("Token deleted.", currentToken);
                    setTokenSentToServer(false);
                    requestPermissionButton.style.display = "block"; // show the button as we no longer have permission and the device token
                    deleteTokenButton.style.display = "none"; // hide the button as we no longer have a token
                    deviceTokenElement.textContent = "No token.";
                })
                .catch((err) => {
                    console.warn("Unable to delete token. ", err);
                });
        })
        .catch((err) => {
            console.warn("Error retrieving registration token. ", err);
        });
}
deleteTokenButton.addEventListener("click", deleteTokenFromFirebase);

/**
 * Send a message to the device using the FCM server using Cloud Messaging API (Legacy)
 */
async function sendForegroundTestMessage() {
    const notification = {
        title: "Foreground message",
        body: "You have focus and received a message!",
        icon: "",
        click_action: "https://vagrant.dev.schoolbox.cloud/greenhouse/donny",
    };
    try {
        const response = await fetch("https://fcm.googleapis.com/fcm/send", {
            method: "POST",
            headers: {
                Authorization: `key=${serverKey}`,
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                notification,
                to: token,
            }),
        });
        console.warn(response);
    } catch (error) {
        console.warn(error);
    }
}
testFrgMessageButton.addEventListener("click", () => {
    void sendForegroundTestMessage();
});

/**
 * Send a message to the device using the FCM server using Cloud Messaging API (Legacy)
 */
async function sendBackgroundTestMessage() {
    await timeout(3000);
    const notification = {
        title: "Background message",
        body: "You didn't have focus and received a message! You should have received a browser notification informing you of this message.",
        icon: "",
        click_action: "https://vagrant.dev.schoolbox.cloud/greenhouse/donny",
    };
    try {
        const response = await fetch("https://fcm.googleapis.com/fcm/send", {
            method: "POST",
            headers: {
                Authorization: `key=${serverKey}`,
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                notification,
                to: token,
            }),
        });
        console.warn(response);
    } catch (error) {
        console.warn(error);
    }
}
testBkgMessageButton.addEventListener("click", () => {
    void sendBackgroundTestMessage();
});

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

window.localStorage.setItem("notificationTitle", "Local storage title");
