/**
 * Shared Vue transitions the show/hide elements
 */

import { slideDown, slideUp } from '@js/util/animateUtils';

/**
 * Slide down to show the element
 *
 * @param { DOMElement }
 */
export async function slideDownOnEnter (el, done) {
    await slideDown(el);
    done(); //to notify Vue of completion
}

/**
 * Slide up to hide the element
 *
 * @param { DOMElement }
 */
export async function slideUpOnLeave (el, done) {
    await slideUp(el);
    done(); //to notify Vue of completion
}