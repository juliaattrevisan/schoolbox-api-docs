/**
 * The different events that can be dispatched by the toast
 */
export enum ToastEvent {
    ACTIVATE = "TOAST__ACTIVATE", //
    TIMEOUT = "TOAST__TIMEOUT", //
}

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
 * The details passed with a ACTIVATE event
 */
export type ToastActivateEventDetail = {
    text: string;
    link?: ToastLink;
};

/**
 * The details passed with a TIMEOUT event
 */
export type ToastTimeoutEventDetail = {
    text: string;
    isSuccess?: boolean;
    duration?: number;
    fadeDuration?: number;
    timeoutCallback?: () => void;
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
    activate: (detail: ToastActivateEventDetail) => Toast;
    timeout: (detail?: ToastTimeoutEventDetail) => Toast;
};

/**
 * The type for window.sbProps.toast
 */
export type ToastProps = {
    /**
     * Create a new toast
     * @param detail - The details of the toast
     * @returns The toast instance
     */
    activate: (detail: ToastActivateEventDetail) => Toast;
    /**
     * Timeout the currently active toast, if one exists
     * @param detail - The details of the timeout
     * @returns The toast instance, or null if no toast is active
     */
    timeout: (detail?: ToastTimeoutEventDetail) => Toast | null;
};

/**
 * Activate the toast with the given text
 * @param text - The text to display in the toast
 * @param link - An optional link for the toast to be clickable
 */
export function activate(text: string, link?: ToastLink): void {
    window.sbProps.toast.activate({ text, link });
    // document.dispatchEvent(
    //     new CustomEvent(ToastEvent.ACTIVATE, {
    //         detail: { text } as ToastActivateEventDetail,
    //     }),
    // );
}

/**
 * Display the timeout toast with the given details
 * @param eventDetail - The details of the timeout event
 */
export function timeout(eventDetail: ToastTimeoutEventDetail): void {
    window.sbProps.toast.timeout(eventDetail);
    // document.dispatchEvent(
    //     new CustomEvent(ToastEvent.TIMEOUT, {
    //         detail: eventDetail,
    //     }),
    // );
}
