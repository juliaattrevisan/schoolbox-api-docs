/**
 * Any error related utils
 */

/**
 * Check if an error is an abort error
 * @param error - the error to be checked
 * @returns - true if abort error
 */
export function isAbortError(error: unknown): boolean {
    return !!error && typeof error === "object" && "name" in error && (error as { name: string }).name === "AbortError";
}

/**
 * Check if an error has a message to display
 * @param error - the error to be checked
 * @returns - true if error has message
 */
export function hasErrorMessage(error: unknown): boolean {
    return !!error && typeof error === "object" && "message" in error;
}
