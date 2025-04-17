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
