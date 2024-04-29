/**
 * Shared Vue transitions the show/hide elements
 */
import { slideDown, slideUp } from "@js/util/animateUtils";

/**
 * Slide down to show the add curriculum display
 * @param {Element} element - The element to animate
 * @param {Function} done - Callback to call when the animation is complete
 */
export function slideDownOnEnter(element: Element, done: () => void) {
    void slideDown(element as HTMLElement).then(() => done());
}

/**
 * Slide up on close or when the curriculum is added
 * @param {Element} element - The element to animate
 * @param {Function} done - Callback to call when the animation is complete
 */
export function slideUpOnLeave(element: Element, done: () => void) {
    void slideUp(element as HTMLElement).then(() => done());
}
