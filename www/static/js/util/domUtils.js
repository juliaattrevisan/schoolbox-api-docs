
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
 * Insert the target node before the reference
 * 
 * @param { DOMElement } element to use as basis
 * @param { DOMElement } element that will be added
 */
 export const insertElementBefore = (referenceElement, targetElement) => {
    referenceElement.parentNode.insertBefore(targetElement, referenceElement);
}
  
/**
 * Insert the target node after the reference
 * 
 * @param { DOMElement } element to use as basis 
 * @param { DOMElement } element that will be added
 */
export const insertElementAfter = (referenceElement, targetElement) => {
    referenceElement.parentNode.insertBefore(targetElement, referenceElement.nextSibling);
}

/**
 * put at the very top of the stack
 *
 * @param { DOMElement } the DOM element
 * 
 */
 export const insertBeforeFirstChild = (element) => {
    const theFirstChild = element.parentNode.firstChild;
    element.parentNode.insertBefore(element, theFirstChild);
}

/**
 * Find the closest element (relative to its mid point) in a NodeList, based on a x,y of a targetPoint 
 * 
 * @param { x, y }  
 * @param { NodeList }  
 * 
 * @returns { DOMElement }   
 */
export const getClosestElement = (targetPoint, elements) => {
    let closestElement = elements[0];
    let minDistance = 99999; //if this doesn't get overwritten we got issues...
    elements.forEach((element) => { //loop through each element and find the closest element using pythagoras theorem
        const elementsMidPoint = getElementsMidPoint(element);
        const distance = Math.sqrt(Math.pow(elementsMidPoint.left - targetPoint.left, 2) + Math.pow(elementsMidPoint.top - targetPoint.top, 2));
        if (distance < minDistance) {
            minDistance = distance;
            closestElement = element;
        }
    });
    return closestElement;
};

/**
 * To get an elements mid point relative to the document
 * 
 * @param { DOMElement }  
 * 
 * @returns { x, y }   
 */
export const getElementsMidPoint = (element) => {
    return {
        left: element.getBoundingClientRect().left + (element.offsetWidth / 2),
        top: (element.getBoundingClientRect().top + window.scrollY) + (element.offsetHeight / 2)
    };
}

/**
 * Is the midpoint of an element above the midpoint of another element
 * 
 * @param { DOMElement }  
 * @param { DOMElement }  
 * 
 * @returns { Boolean }  
 */
export const isElementAboveOtherElement = (firstElement, secondElement) => {
    return (getElementsMidPoint(firstElement).top > getElementsMidPoint(secondElement).top);
}

/**
 * Is the midpoint of an element below the midpoint of another element
 * 
 * @param { DOMElement }  
 * @param { DOMElement }  
 * 
 * @returns { Boolean }  
 */
export const isElementBelowOtherElement = (firstElement, secondElement) => {
    return (getElementsMidPoint(firstElement).top < getElementsMidPoint(secondElement).top);
}

/**
 * Is the midpoint of an element below the midpoint of another element
 * 
 * @param { DOMElement }  
* @param { Styles } for example { position: 'absolute', top: '20px' } 
 * 
 */
export const setStylesOnElement = (element,styles) => {
    Object.assign(element.style, styles);
}