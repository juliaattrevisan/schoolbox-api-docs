/*
 * Animation helpers based on GSAP 
 */

import { gsap } from 'gsap/all';
import { setAndGetAttribute } from '@js/util/domUtils';

const PADDING_TOP_ATTRIBUTE = 'data-padding-top';
const PADDING_BOTTOM_ATTRIBUTE = 'data-padding-bottom';

/**
 * Replicates jQuery's slideDown - Display the element with a sliding motion.
 * Assumes the element is hidden with display: none;
 * 
 * @params { DOMElement }
 * @params { Number } in seconds, so 0.3 is 300ms
 */
export const slideDown = async (element, duration = 0.3) => {
    
    if (element.classList.contains('hide')) element.classList.remove('hide');

    element.style.display = 'block';
    element.style.height = 'auto';
    element.style.overflow = 'hidden';
    
    const paddingTop = getPaddingTop(element);
    element.style.paddingTop = 0;
    
    const paddingBottom = getPaddingBottom(element);
    element.style.paddingBottom = 0;
    
    const targetHeight = element.offsetHeight + paddingTop + paddingBottom;
    element.style.height = 0;

    return gsap.to(element, { 
        height: targetHeight, 
        paddingTop: paddingTop, 
        paddingBottom: paddingBottom,
        overwrite: 'auto', 
        duration: duration,
        ease: 'power1.inOut',
        onComplete: () => element.style.overflow = 'visible'
    } );

}

/**
 * Set the padding top for reference on the element in case a slideUp is toggled before the animation has been completed
 * 
 * @params { DOMElement }
 * 
 * @returns { Number } 
 */
const getPaddingTop = (element) => {
    if (element.getAttribute(PADDING_TOP_ATTRIBUTE)) {
        return parseFloat(element.getAttribute(PADDING_TOP_ATTRIBUTE));
    } else {
        return parseFloat(setAndGetAttribute(element, PADDING_TOP_ATTRIBUTE, parseFloat(getComputedStyle(element).paddingTop)));
    }
}

/**
 * Set the padding bottom for reference on the element in case a slideUp is toggled before the animation has been completed
 * 
 * @params { DOMElement }
 * 
 * @returns { Number } 
 */
const getPaddingBottom = (element) => {
    if (element.getAttribute(PADDING_BOTTOM_ATTRIBUTE)) {
        return parseFloat(element.getAttribute(PADDING_BOTTOM_ATTRIBUTE));
    } else {
        return parseFloat(setAndGetAttribute(element, PADDING_BOTTOM_ATTRIBUTE, parseFloat(getComputedStyle(element).paddingBottom)));
    }
}

/**
 * Replicates jQuery's slideUp - Hide the element with a sliding motion.
 * 
 * @params { DOMElement }
 * @params { Number } in seconds, so 0.3 is 300ms
 */
export const slideUp = (element, duration = 0.3) => {
    element.style.overflow = 'hidden';
    const paddingTop = getPaddingTop(element);
    const paddingBottom = getPaddingBottom(element);
    return gsap.to(element, {  
        height: 0, 
        paddingTop: 0, 
        paddingBottom: 0, 
        overwrite: 'auto', 
        duration: duration, 
        ease: 'power1.in',
        onComplete: () => onSlideUpComplete(element, paddingTop, paddingBottom)
    } );
}

/**
 * Revert to it's original state
 * 
 * @params { DOMElement }
 * @params { Number }
 * @params { Number }
 */
const onSlideUpComplete = (element, paddingTop, paddingBottom) => {
    element.style.display = 'none';
    element.style.height = 'auto';
    element.style.overflow = 'visible';
    element.style.paddingTop = `${paddingTop}px`;
    element.style.paddingBottom = `${paddingBottom}px`;
    element.removeAttribute(PADDING_TOP_ATTRIBUTE);
    element.removeAttribute(PADDING_BOTTOM_ATTRIBUTE);
}
