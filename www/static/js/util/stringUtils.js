/*
 * Any utils that manipulate strings
 *
 */

/**
 * shorten a string if it's over the max length
 *
 * @param { string }
 * @param { number }
 *
 * @returns { string }
 */
export function shortenLength (string, maxLength) {
    return string.length < maxLength ? string : `${string.substring(0, maxLength)}...`;
}