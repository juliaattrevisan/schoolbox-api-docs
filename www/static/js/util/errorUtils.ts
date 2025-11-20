/**
 * Any error related utils
 */

/**
 * Check if an error is an abort error
 * @param error - the error to be checked
 * @returns - true if abort error
 */
export function isAbortError(error: unknown): boolean {
    if (!error || typeof error !== "object") {
        return false;
    }
    // Check for AbortError name
    if ("name" in error && (error as { name: string }).name === "AbortError") {
        return true;
    }
    // Check for specific abort message
    if ("message" in error && (error as { message: string }).message === "signal is aborted without reason") {
        return true;
    }
    return false;
}

/**
 * Check if an error has a message to display
 * @param error - the error to be checked
 * @returns - true if error has message
 */
export function hasErrorMessage(error: unknown): boolean {
    return !!error && typeof error === "object" && "message" in error;
}

/**
 * Exhaustive type checking for switch statements
 * @param x - The value that should never be reached
 * @param message - The message to log if this happens to be reached (optional)
 * @throws always
 */
export function assertUnreachable(x: never, message?: string): never {
    // only console.error if message is undefined, since if it's defined we likely are handling this intentionally elsewhere
    if (message === undefined) console.error("Unreachable code reached", x);

    throw new Error(message || "Unreachable code reached");
}
