/*
 * Any js specific utils
 *
 */

/**
* to a timeout in an async/await method
* 
* @param { Number } milliseconds
*
* @returns { Promise } 
*/ 
export const timeout = (ms) => {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
* check for an object
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