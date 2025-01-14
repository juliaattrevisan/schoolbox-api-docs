/**
 * To determine if the user is on an iOS or Android device (or neither)
 * @returns The device platform of the user
 */
export function getUserPlatform(): "iOS" | "Android" | "Browser" {
    // iOS
    const iOSValidPlatforms = ["iPad Simulator", "iPhone Simulator", "iPod Simulator", "iPad", "iPhone", "iPod"];
    if (
        iOSValidPlatforms.includes(navigator.platform) ||
        // iPad on iOS 13 detection
        (navigator.userAgent.includes("Mac") && "ontouchend" in document)
    ) {
        return "iOS";
    }

    // ANDROID
    if (/android/i.test(navigator.userAgent)) {
        return "Android";
    }

    return "Browser";
}

/**
 * To determine if the user is currently inside a PWA
 * @returns True if in PWA, false otherwise
 */
export function isPWA(): boolean {
    return window.matchMedia("(display-mode: standalone)").matches;
}

/**
 * Whether the browser supports notifications
 * @returns True if the browser supports notifications, false otherwise
 */
export function supportsNotifications(): boolean {
    return "Notification" in window;
}

/**
 * The BeforeInstallPromptEvent is the interface of the beforeinstallprompt event
 * fired at the Window object before a user is prompted to "install" a website to a home screen on mobile.
 *
 * This is a non-standard event and is not supported in all browsers yet, but it is useful for Chrome
 * Since it is non-standard, we need to define the interface ourselves
 *
 * https://developer.mozilla.org/en-US/docs/Web/API/BeforeInstallPromptEvent/BeforeInstallPromptEvent
 */
export interface BeforeInstallPromptEvent extends Event {
    /**
     * Returns an array of string items containing the platforms on which the event was dispatched.
     * This is provided for user agents that want to present a choice of versions to the user such as,
     * for example, "web" or "play" which would allow the user to choose between a web version
     * or an Android version.
     */
    readonly platforms: string[];

    /**
     * Returns a Promise that resolves to an object describing the user's choice
     * when they were prompted to install the app.
     */
    readonly userChoice: Promise<{
        // A string indicating whether the user chose to install the app or not. It must be one of the following values:
        // - "accepted": The user installed the app
        // - "dismissed": The user did not install the app
        outcome: "accepted" | "dismissed";
        // If the user chose to install the app, this is a string naming the selected platform,
        // which is one of the values from the BeforeInstallPromptEvent.platforms property.
        // If the user chose not to install the app, this is an empty string.
        platform: string;
    }>;

    /**
     * Allows a developer to show the install prompt at a time of their own choosing.
     * This method must be called in the event handler for a user action (such as a button click) and may only be
     * called once on a given BeforeInstallPromptEvent instance.
     */
    prompt(): Promise<void>;
}
