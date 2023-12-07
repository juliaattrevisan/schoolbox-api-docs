/*
 * CSS class utilities
 *
 */

/**
 * Search an elements classList and remove any classes that contain the search reference
 * @param { HTMLElement } element - the DOM element to apply the removals to
 * @param { string } search - the string to search for
 */
export const removeClassesThatContain = (element: HTMLElement, search: string) => {
    const { classList } = element;
    classList.forEach((className) => {
        if (className.includes(search)) {
            classList.remove(className);
        }
    });
};
