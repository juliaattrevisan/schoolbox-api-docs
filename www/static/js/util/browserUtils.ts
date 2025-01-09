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

