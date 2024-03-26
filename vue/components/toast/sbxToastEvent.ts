/**
 * The different events that can be dispatched by the toast
 */
export enum ToastEvent {
    ACTIVATE = "TOAST__ACTIVATE", //
    TIMEOUT = "TOAST__TIMEOUT", //
}

/**
 * The details passed with a ACTIVATE event
 */
export type ToastActivateEventDetail = {
    text: string;
};

/**
 * The details passed with a TIMEOUT event
 */
export type ToastTimeoutEventDetail = {
    text: string;
    isSuccess: boolean | undefined;
    duration: number | undefined;
    fadeDuration: number | undefined;
    timeoutCallback: () => void | undefined;
};
