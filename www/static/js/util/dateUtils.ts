/*
 * Any date related utils, typically linked to our
 * globally exposed window.dayjs instance.
 */

import type { Dayjs } from "dayjs";

/** Generally the time format we use to get times sent from the backend */
export type DateRFC3339 = `${number}-${number}-${number}T${number}:${number}:${number}+${number}:${number}`;

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

/**
 * Format a date string as a relative time (e.g., "3 days ago")
 * @param datetime - The date-time to display in this relative time input
 * @param fullDiff - Is a full diff (e.g. the most significant component) displayed? If not: show diff as days only
 * @returns - The formatted relative time string
 */
export function getRelativeTime(datetime: Dayjs | DateRFC3339, fullDiff: boolean = false): string {
    const now = window.dayjs();
    const dayjsDatetime = window.dayjs(datetime);

    /**
     * Full diff format (e.g. hours, minutes, seconds):
     * just output whatever the dayJS diff would output, which is the
     * most significant component of the diff.
     */
    if (fullDiff) {
        return window.dayjs.duration(dayjsDatetime.diff(now)).humanize(true);
    }

    /**
     * Standard diff format:
     * Just output days, with "today", "yesterday" or "tomorrow" if
     * appropriate.
     */
    const dayDiff = dayjsDatetime.diff(now.startOf("d"), "d");

    /**
     * We determine a relative time equivalence based on how different
     * $datetime was, compared to today. Anything outside the (-1, 1) range
     * gets a non-relative format
     */
    if (dayDiff === 0) {
        return "today";
    }

    if (dayDiff === -1) {
        return "yesterday";
    }

    if (dayDiff === 1) {
        return "tomorrow";
    }

    return window.dayjs.duration(dayDiff, "d").humanize(true);
}
