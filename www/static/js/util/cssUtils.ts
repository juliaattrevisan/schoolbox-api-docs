/*
 * CSS class utilities
 *
 */

/**
 * Search an elements classList and remove any classes that contain the search reference
 * @param {HTMLElement} element - the DOM element to apply the removals to
 * @param {string} search - the string to search for
 */
export function removeClassesThatContain(element: HTMLElement, search: string) {
    const { classList } = element;
    classList.forEach((className) => {
        if (className.includes(search)) {
            classList.remove(className);
        }
    });
}

/**
 * Return a colour between a gradient of red (0) to green (10) based on the current count and total count
 * @param {number} currentCount - the current count of the total
 * @param {number} totalCount - the total count
 * @returns {string} - the class name of the gradient
 */
export function getGradient(currentCount: number, totalCount: number) {
    const gradient = (currentCount / totalCount) * 100;
    let index = Math.floor(gradient / 10);
    const max = 10;
    const min = 0;
    if (index > max) {
        index = max;
    }
    if (index < min) {
        index = min;
    }
    return `gradient-${index}`;
}
