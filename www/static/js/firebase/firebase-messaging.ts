// JS includes required by the Firebase service worker.
import { initializeApp } from "@firebase/app";
import { getMessaging, onBackgroundMessage } from "@firebase/messaging/sw";

export { initializeApp, getMessaging, onBackgroundMessage };
