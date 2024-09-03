/**
 * Any shared Vue transitions that are used by multiple components
 * https://vuejs.org/guide/built-ins/transition.html#javascript-hooks
 */

import { slideDown, slideUp } from "@js/util/animateUtils";

/**
 * Slide down to show the add curriculum display
 * @param element - The element to animate
 * @param done - Callback to call when the animation is complete
 */
export function slideDownOnEnter(element: Element, done: () => void): void {
    void slideDown(element as HTMLElement).then(() => done());
}

/**
 * Slide up on close or when the curriculum is added
 * @param element - The element to animate
 * @param done - Callback to call when the animation is complete
 */
export function slideUpOnLeave(element: Element, done: () => void): void {
    void slideUp(element as HTMLElement).then(() => done());
}
