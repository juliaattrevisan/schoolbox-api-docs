
/**
 * returns an integer indicating the position of the 
 * passed element relative to the original collection
 * replacement for $(el).index
 *
 * @param { DOMElement } the DOM element to find the index of
 * 
 * @return { Number } position of the DOM element
 */
export const getIndex = (element) => {
    if (!element) {
        return -1;
    }
    let i = 0;
    while (element = element.previousElementSibling) {
        i++;
    }
    return i;
}


/**
 * empty the element of children
 * replacement for $(el).empty
 *
 * @param { DOMElement } the DOM element to empty
 * 
 */
export const empty = (element) => {
    while (element.firstChild) {
        element.removeChild(element.firstChild);
    }
}

/**
 * switch elements, changing stacking order
 *
 * @param { DOMElement } the first DOM element
 * @param { DOMElement } the second DOM element
 * 
 */
export const swapElements = (firstElement, secondElement) => {
    firstElement.parentNode.insertBefore(firstElement, secondElement);
}

/**
 * put at the very bottom of the stack
 *
 * @param { DOMElement } the DOM element
 * 
 */
export const insertBeforeFirstChild = (element) => {
    const theFirstChild = element.parentNode.firstChild;
    element.parentNode.insertBefore(element, theFirstChild);
}