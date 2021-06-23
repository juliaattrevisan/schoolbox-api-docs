export const ACHIEVEMENT_EXPIRY_DAY = 1;
export const ACHIEVEMENT_EXPIRY_WEEK = 7;
export const ACHIEVEMENT_EXPIRY_FORTNIGHT = 14;
export const ACHIEVEMENT_EXPIRY_MONTH = 31;
export const ACHIEVEMENT_EXPIRY_HALF_YEAR = 183;
export const ACHIEVEMENT_EXPIRY_YEAR = 365;
export const ACHIEVEMENT_EXPIRY_OPTIONS = [
    {
        label: 'Forever',
        value: null,
    },
    {
        label: '1 day',
        value: ACHIEVEMENT_EXPIRY_DAY,
    },
    {
        label: '1 week',
        value: ACHIEVEMENT_EXPIRY_WEEK,
    },
    {
        label: '2 weeks',
        value: ACHIEVEMENT_EXPIRY_FORTNIGHT,
    },
    {
        label: '1 month',
        value: ACHIEVEMENT_EXPIRY_MONTH,
    },
    {
        label: '6 months',
        value: ACHIEVEMENT_EXPIRY_HALF_YEAR,
    },
    {
        label: '1 year',
        value: ACHIEVEMENT_EXPIRY_YEAR,
    }
];

/**
 * Translates an achievement expiry to a label.
 *
 * @param {Number|null} achievementExpiry
 * @return {string|null}
 */
export function translateAchievementExpiryToLabel(achievementExpiry) {
    let match = ACHIEVEMENT_EXPIRY_OPTIONS.find(v => v.value === achievementExpiry);
    if (match) {
        return match.label;
    }
    return null;
}
