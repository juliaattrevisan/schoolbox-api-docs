/*
 * Any js specific utils
 *
 */

/**
 * To a timeout in an async/await method
 *
 * @param { Number } milliseconds
 *
 * @returns { Promise }
 */
export const timeout = (ms) => {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Check for an object
 * https://stackoverflow.com/questions/8511281/check-if-a-value-is-an-object-in-javascript/8511350#8511350
 *
 * @param { Object }
 *
 * @returns { Boolean }
 */
export const isObject = (variable) => {
    return typeof variable === 'object' &&
        !Array.isArray(variable) &&
        variable !== null;
}

/**
 * Convert an Object of Objects to an Array
 *
 * @param { Object }
 *
 * @returns { Array }
 */
export const convertObjectToArray = (object) => {
    return Object.keys(object).map((key) => object[key]);
}

/**
 * Escape a regular expression
 * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Regular_Expressions#escaping
 *
 * @param { String }
 *
 * @returns { String }
 */
export const escapeRegExp = (string) => {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 *
 *
 * @returns { String }
 */
export const randomId = (string) => {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}