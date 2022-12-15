/**
 * Any utils that manipulate strings
 */

/**
 * shorten a string if it's over the max length
 *
 * @param {string} string - the string to shorten
 * @param {number} maxLength - the max length the string should be shortened to
 * @returns {string} shortened string
 */
export function shortenLength(string: string, maxLength: number): string {
    return string.length < maxLength ? string : `${string.substring(0, maxLength)}...`;
}

/**
 * capitalize the first letter of a string
 *
 * @param {string} string - the string to capitalize
 * @returns {string} string with first letter capitalized
 */
export function capitalizeFirstLetter(string: string): string {
    return string.charAt(0).toUpperCase() + string.slice(1);
}
