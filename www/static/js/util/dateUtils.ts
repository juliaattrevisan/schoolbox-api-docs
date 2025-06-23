/*
 * Any date related utils, typically linked to our
 * globally exposed window.dayjs instance.
 */

/**
 * Get a human-readable string representing the time from now
 * @param unixTimestamp - The Unix timestamp to convert
 * @returns - A human-readable string representing the time from now
 * For example, "3 days ago", "2 hours ago", etc.
 */
export function getFromNow(unixTimestamp: number): string {
    return window.dayjs.unix(unixTimestamp).fromNow();
}
