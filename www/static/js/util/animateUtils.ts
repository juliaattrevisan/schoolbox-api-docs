/*
 * Animation helpers based on GSAP
 */

import { gsap } from "gsap/all";
import { setAndGetAttribute } from "@js/util/domUtils";

const PADDING_TOP_ATTRIBUTE = "data-padding-top";
const PADDING_BOTTOM_ATTRIBUTE = "data-padding-bottom";

/**
 * Set the padding top for reference on the element in case a slideUp is toggled before the animation has been completed
 * @param {HTMLElement} element - the element to retrieve the padding top from
 * @returns {number} - the padding top
 */
function getPaddingTop(element: HTMLElement) {
    if (element.getAttribute(PADDING_TOP_ATTRIBUTE)) {
        return parseFloat(element.getAttribute(PADDING_TOP_ATTRIBUTE) as string);
    }
    return parseFloat(
        setAndGetAttribute(
            element,
            PADDING_TOP_ATTRIBUTE,
            parseFloat(getComputedStyle(element).paddingTop).toString(),
        ) as string,
    );
}

/**
 * Set the padding bottom for reference on the element in case a slideUp is toggled before the animation has been completed
 * @param {HTMLElement} element - the element to retrieve the padding bottom from
 * @returns {number} - the padding bottom
 */
function getPaddingBottom(element: HTMLElement) {
    if (element.getAttribute(PADDING_BOTTOM_ATTRIBUTE)) {
        return parseFloat(element.getAttribute(PADDING_BOTTOM_ATTRIBUTE) as string);
    }
    return parseFloat(
        setAndGetAttribute(
            element,
            PADDING_BOTTOM_ATTRIBUTE,
            parseFloat(getComputedStyle(element).paddingBottom).toString(),
        ) as string,
    );
}

/**
 * Revert to it's original state
 * @param {HTMLElement} element - the element to revert
 */
function onSlideDownComplete(element: HTMLElement) {
    element.style.height = "auto";
    element.style.overflow = "visible";
}

/**
 * Replicates jQuery's slideDown - Display the element with a sliding motion.
 * Assumes the element is hidden with display: none;
 * @param {HTMLElement} element - the element to slide down
 * @param {number} duration - in seconds, so 0.3 is 300ms
 * @param  {?string} eventToFireWhenElementVisible - name of CustomEvent to be fired when the element first becomes visible.
 * @returns {Promise} - resolves when the animation is complete
 */
export async function slideDown(element: HTMLElement, duration = 0.3, eventToFireWhenElementVisible = null) {
    if (element.classList.contains("hide")) {
        element.classList.remove("hide");
    }

    element.style.display = (element.getAttribute("data-display") as string) ?? "block";
    element.style.height = "auto";
    element.style.overflow = "hidden";

    if (eventToFireWhenElementVisible !== null) {
        element.dispatchEvent(new CustomEvent(eventToFireWhenElementVisible));
    }

    const paddingTop = getPaddingTop(element);
    element.style.paddingTop = "0";

    const paddingBottom = getPaddingBottom(element);
    element.style.paddingBottom = "0";

    const targetHeight = element.offsetHeight + paddingTop + paddingBottom;
    element.style.height = "0";

    return gsap.to(element, {
        height: targetHeight,
        paddingTop,
        paddingBottom,
        overwrite: "auto",
        duration,
        ease: "power1.out",
        onComplete: () => onSlideDownComplete(element),
    });
}

/**
 * Revert to it's original state
 * @param {HTMLElement} element - the element to revert
 * @param {number} paddingTop - the padding top to revert to
 * @param {number} paddingBottom - the padding bottom to revert to
 */
function onSlideUpComplete(element: HTMLElement, paddingTop: number, paddingBottom: number) {
    element.style.display = "none";
    element.style.height = "auto";
    element.style.overflow = "visible";
    element.style.paddingTop = `${paddingTop}px`;
    element.style.paddingBottom = `${paddingBottom}px`;
    element.removeAttribute(PADDING_TOP_ATTRIBUTE);
    element.removeAttribute(PADDING_BOTTOM_ATTRIBUTE);
}

/**
 * Replicates jQuery's slideUp - Hide the element with a sliding motion.
 * @param {HTMLElement} element - the element to slide up
 * @param {number} duration - in seconds, so 0.3 is 300ms
 * @returns {Promise} - resolves when the animation is complete
 */
export async function slideUp(element: HTMLElement, duration = 0.3) {
    element.style.overflow = "hidden";
    element.setAttribute("data-display", getComputedStyle(element).display);
    const paddingTop = getPaddingTop(element);
    const paddingBottom = getPaddingBottom(element);
    return gsap.to(element, {
        height: 0,
        paddingTop: 0,
        paddingBottom: 0,
        overwrite: "auto",
        duration,
        ease: "power1.in",
        onComplete: () => onSlideUpComplete(element, paddingTop, paddingBottom),
    });
}

/**
 * Animate the rotation of an element
 * @param {HTMLElement} element - the element to rotate
 * @param {number} rotation - in degrees
 * @param {string} transformOrigin - the transform origin
 * @param {number} duration - in seconds
 * @returns {Promise} - resolves when the animation is complete
 */
export function animateRotation(element: HTMLElement, rotation: number, transformOrigin: string, duration: number) {
    return gsap.to(element, {
        rotation,
        transformOrigin,
        duration,
    });
}
