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
