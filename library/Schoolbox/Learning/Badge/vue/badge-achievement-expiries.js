export const EXPIRY_DAY = 1;
export const EXPIRY_WEEK = 7;
export const EXPIRY_FORTNIGHT = 14;
export const EXPIRY_MONTH = 31;
export const EXPIRY_HALF_YEAR = 183;
export const EXPIRY_YEAR = 365;
export const EXPIRY_OPTIONS = [
    {
        label: 'Forever',
        value: null,
    },
    {
        label: '1 day',
        value: EXPIRY_DAY,
    },
    {
        label: '1 week',
        value: EXPIRY_WEEK,
    },
    {
        label: '2 weeks',
        value: EXPIRY_FORTNIGHT,
    },
    {
        label: '1 month',
        value: EXPIRY_MONTH,
    },
    {
        label: '6 months',
        value: EXPIRY_HALF_YEAR,
    },
    {
        label: '1 year',
        value: EXPIRY_YEAR,
    }
];

/**
 * Translates an expiry value (as a number of days) to a label.
 *
 * @param {Number|null} expiry
 * @return {string|null}
 */
export function translateExpiryToLabel(expiry) {
    let match = EXPIRY_OPTIONS.find(v => v.value === expiry);
    if (match) {
        return match.label;
    }
    return null;
}

export function calculateExpiryDate(expiry) {
    let date = new Date();
}
