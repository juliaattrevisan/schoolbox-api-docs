/*
 * CSS class utilities
 *
 */

/**
 * Remove classes from the target element and any descendants
 * @param element - the target element
 * @param classNames - the classes to remove
 */
export function removeClasses(element: HTMLElement, classNames: string[]): void {
    // Remove classes from the target element
    classNames.forEach((className) => {
        if (element.classList.contains(className)) {
            element.classList.remove(className);
        }
    });
    // Remove classes from all descendants
    classNames.forEach((className) => {
        element.querySelectorAll(`.${className}`).forEach((descendant) => {
            descendant.classList.remove(className);
        });
    });
}

/**
 * Search an elements classList and remove any classes that contain the search reference
 * @param element - the DOM element to apply the removals to
 * @param search - the string to search for
 */
export function removeClassesThatContain(element: HTMLElement, search: string): void {
    const { classList } = element;
    classList.forEach((className) => {
        if (className.includes(search)) {
            classList.remove(className);
        }
    });
}

/**
 * Return a colour between a gradient of red (0) to green (10) based on the current count and total count
 * @param currentCount - the current count of the total
 * @param totalCount - the total count
 * @returns - the class name of the gradient
 */
export function getGradient(currentCount: number | null, totalCount: number | null): string {
    if (typeof currentCount !== "number" || typeof totalCount !== "number") {
        return "gradient-none";
    }
    if (currentCount === 0 && totalCount === 0) {
        return "gradient-none";
    }
    if (currentCount > totalCount) {
        return "gradient-none";
    }
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
