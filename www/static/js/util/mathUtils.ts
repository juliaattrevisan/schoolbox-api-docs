/**
 * Any math related utils
 */

/**
 * Check if a number is a whole number
 * @param {number} value - the number to check
 * @returns {boolean} - true if the number is a whole number
 */
export function isRounded(value: number): boolean {
    if (typeof value !== "number") {
        return false;
    }
    return value === Math.floor(value);
}

/**
 * Calculate the percentage of a value
 * @param {number} value - the value to calculate the percentage of
 * @param {number} max - the maximum value
 * @param {number} decimalPlaces - the number of decimal places to round to
 * @returns {string} - the percentage, for example 30% or 45.67%
 */
export function getPercentage(value: number, max: number, decimalPlaces: number = 2): string {
    const invalidValue = "0%";
    // check if both are numbers
    if (typeof value !== "number" || typeof max !== "number") {
        return invalidValue;
    }
    // check if either are negative
    if (value < 0 || max <= 0) {
        return invalidValue;
    }
    // check if value is greater than max
    if (max < value) {
        return invalidValue;
    }
    const percentageNum = (value / max) * 100;
    // If sum isn't a whole number then return to decimalPlaces
    if (!isRounded(percentageNum)) {
        return `${percentageNum.toFixed(decimalPlaces)}%`;
    }
    return `${Math.round(percentageNum)}%`;
}
