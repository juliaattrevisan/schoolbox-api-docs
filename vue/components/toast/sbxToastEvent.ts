/**
 * The different states of a toast
 */
export enum ToastState {
    HIDDEN = "hidden",
    PENDING = "pending",
    DONE = "done",
    FAILED = "failed",
}

export type ToastLink = {
    text: string;
    href: string;
};

/**
 * A toast instance
 */
export type Toast = {
    text: string;
    state: ToastState;
    link: ToastLink | null;
    fadeDuration: number;
    hidden: boolean; // I suppose this allows you to resurrect a toast that was timed out
    currentTimeout: {
        timeoutId: number;
        callback?: () => void;
    } | null;
    activate: (text: string, link?: ToastLink) => Toast;
    timeout: (detail?: ToastTimeoutDetail) => Toast;
};

/**
 * The details passed when timing out a toast
 */
export type ToastTimeoutDetail = {
    text: string;
    isSuccess?: boolean;
    duration?: number;
    fadeDuration?: number;
    timeoutCallback?: () => void;
};

// The following two functions (activate and timeout) are wrappers for sbProps
// so we can continue to import them from sbxToastEvent as per usual...

/**
 * Activate the toast with the given text
 * @param text - The text to display in the toast
 * @param link - An optional link for the toast to be clickable
 * @returns The toast instance
 */
export function activate(text: string, link?: ToastLink): Toast {
    return window.sbProps.toastActivate(text, link);
}

/**
 * Timeout the currently active toast, if one exists
 * Note, it is recommended to use the `timeout` method on the toast instance instead
 * to avoid accidentally timing out another toast created by another script asynchronously
 * @param eventDetail - The details of the timeout
 */
export function timeout(eventDetail: ToastTimeoutDetail): void {
    window.sbProps.toastTimeout(eventDetail);
}
