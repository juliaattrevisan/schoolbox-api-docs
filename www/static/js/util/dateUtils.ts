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

/**
 * Formats a date string using Day.js in the format configured in
 * config.date_local and config.date_time
 * @param date - The date to format
 * @returns - The formatted date
 */
export function getDayjsDatetime(date: string): string {
    return window.dayjs(date).format(window.dateDisplayFormats.dayjsDatetime);
}
