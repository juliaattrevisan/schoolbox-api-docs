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