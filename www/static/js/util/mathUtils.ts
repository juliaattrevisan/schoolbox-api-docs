/**
 * Any math related utils
 */

/**
 * Check if a number is a whole number
 * @param value - the number to check
 * @returns - true if the number is a whole number
 */
export function isWholeNumber(value: number): boolean {
    if (typeof value !== "number") {
        return false;
    }
    return value === Math.floor(value);
}

/**
 * Calculate the percentage (from 100%) of a value
 * @param value - the value to calculate the percentage of
 * @param max - the maximum value
 * @param decimalPlaces - the number of decimal places to round to
 * @returns - the percentage, for example 30% or 45.67%
 */
export function getPercentage(value: number, max: number, decimalPlaces: number = 2): string | undefined {
    // check if both are numbers
    if (typeof value !== "number" || typeof max !== "number") {
        return undefined;
    }
    // check if either are negative
    if (value < 0 || max <= 0) {
        return undefined;
    }
    // check if value is greater than max
    if (max < value) {
        return undefined;
    }
    const percentageNum = (value / max) * 100;
    // if percentage is a whole number then return as a whole number
    if (isWholeNumber(percentageNum)) {
        return `${Math.round(percentageNum)}%`;
    }
    // if sum isn't a whole number then return to decimalPlaces, also removing any trailing zeros using parseFloat
    return `${parseFloat(percentageNum.toFixed(decimalPlaces))}%`;
}
