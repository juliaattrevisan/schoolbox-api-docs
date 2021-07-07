/**
 * Constants and functions for helping with badge expiry dates.
 */

export const EXPIRY_NEVER = null;
export const EXPIRY_DAY = 1;
export const EXPIRY_WEEK = 7;
export const EXPIRY_FORTNIGHT = 14;
export const EXPIRY_MONTH = 31;
export const EXPIRY_QUARTER_YEAR = 92;
export const EXPIRY_HALF_YEAR = 183;
export const EXPIRY_YEAR = 365;
export const EXPIRY_OPTIONS = [
    {
        expiryLabel: 'Does not expire',
        lifetimeLabel: 'Forever',
        value: EXPIRY_NEVER,
    },
    {
        expiryLabel: '1 day',
        lifetimeLabel: '1 day',
        value: EXPIRY_DAY,
    },
    {
        expiryLabel: '1 week',
        lifetimeLabel: '1 week',
        value: EXPIRY_WEEK,
    },
    {
        expiryLabel: '2 weeks',
        lifetimeLabel: '2 weeks',
        value: EXPIRY_FORTNIGHT,
    },
    {
        expiryLabel: '1 month',
        lifetimeLabel: '1 month',
        value: EXPIRY_MONTH,
    },
    {
        expiryLabel: '3 months',
        lifetimeLabel: '3 months',
        value: EXPIRY_QUARTER_YEAR,
    },
    {
        expiryLabel: '6 months',
        lifetimeLabel: '6 months',
        value: EXPIRY_HALF_YEAR,
    },
    {
        expiryLabel: '1 year',
        lifetimeLabel: '1 year',
        value: EXPIRY_YEAR,
    }
];

/**
 * Translates an expiry value (as a number of days) to a label for when it will
 * expire.
 *
 * @param {Number|null} expiry
 * @return {string|null}
 */
export function translateDaysToExpiryLabel(expiry) {
    let match = EXPIRY_OPTIONS.find(v => v.value === expiry);
    if (match) {
        return match.expiryLabel;
    }
    return null;
}

/**
 * Translates an expiry value (as a number of days) to a label representing its
 * lifetime.
 *
 * The difference between this and `translateDaysToExpiryLabel` is how a
 * non-expiring badge is displayed:
 * * Expiry label: "does not expire"
 * * Lifetime label: "forever"
 *
 * @param {Number|null} expiry
 * @return {string|null}
 */
export function translateDaysToLifetimeLabel(expiry) {
    let match = EXPIRY_OPTIONS.find(v => v.value === expiry);
    if (match) {
        return match.lifetimeLabel;
    }
    return null;
}

/**
 * Calculates an actual expiry date from a number of days, with an optional date
 * to calculate from.
 *
 * @param {Number|null} expiry
 * @param {Date|null} fromDate
 * @returns {Date|null}
 */
export function calculateExpiryDate(expiry, fromDate) {
    if (expiry === EXPIRY_NEVER) {
        return null;
    }
    let date = fromDate instanceof Date ? new Date(fromDate.getTime()) : new Date();
    date.setDate(date.getDate() + expiry);
    return date;
}
