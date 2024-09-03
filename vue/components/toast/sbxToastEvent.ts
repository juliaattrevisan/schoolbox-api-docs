/**
 * The different events that can be dispatched by the toast
 */
export enum ToastEvent {
    ACTIVATE = "TOAST__ACTIVATE", //
    TIMEOUT = "TOAST__TIMEOUT", //
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
 * Activate the toast with the given text
 * @param {string} text - The text to display in the toast
 */
export function activate(text: string) {
    document.dispatchEvent(
        new CustomEvent(ToastEvent.ACTIVATE, {
            detail: { text } as ToastActivateEventDetail,
        }),
    );
}

/**
 * Display the timeout toast with the given details
 * @param {ToastTimeoutEventDetail} eventDetail - The details of the timeout event
 */
export function timeout(eventDetail: ToastTimeoutEventDetail) {
    document.dispatchEvent(
        new CustomEvent(ToastEvent.TIMEOUT, {
            detail: eventDetail,
        }),
    );
}
