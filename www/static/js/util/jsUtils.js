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
 * generate a random string to ensure an id is unique
 * from: https://stackoverflow.com/questions/105034/how-do-i-create-a-guid-uuid
 *
 * @returns { String }
 */
export const generateUUID = () => {
    let d = new Date().getTime();
    let d2 = (performance && performance.now && (performance.now() * 1000)) || 0;
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        let r = Math.random() * 16;
        if (d > 0) {
            r = (d + r) % 16 | 0;
            d = Math.floor(d / 16);
        } else {
            r = (d2 + r) % 16 | 0;
            d2 = Math.floor(d2 / 16);
        }
        return (c == 'x' ? r : (r & 0x7 | 0x8)).toString(16);
    });
};