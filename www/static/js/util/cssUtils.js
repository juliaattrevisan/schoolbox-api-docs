/*
 * CSS class utilities
 *
 */

/**
 * Search an elements classList and remove any classes that contain the search reference
 *
 * @params { Element } the DOM element to apply the removals to
 * @params { String } the string to search for
 */
export const removeClassesThatContain = (element, search) => {
    const classList = element.classList;
    classList.forEach((className) => {
        if (className.includes(search)) {
            classList.remove(className);
        }
    });
}