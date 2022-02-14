/*
 * Basic DOM manipulation utilities
 *
 */

/**
 * returns an integer indicating the position of the 
 * passed element relative to the original collection
 * replacement for $(el).index
 *
 * @param { Element } the DOM element to find the index of
 * 
 * @return { Number } position of the DOM element
 */
export function getIndex (element) {
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
 * @param { Element } the DOM element to empty
 * 
 */
export function empty (element) {
    while (element.firstChild) {
        element.removeChild(element.firstChild);
    }
}

/**
 * switch elements, changing stacking order
 *
 * @param { Element } the first DOM element
 * @param { Element } the second DOM element
 * 
 */
export function swapElements (firstElement, secondElement) {
    firstElement.parentNode.insertBefore(firstElement, secondElement);
}

/**
 * Insert the target node before the reference
 * 
 * @param { Element } element to use as basis
 * @param { Element } element that will be added
 */
export function insertElementBefore (referenceElement, targetElement) {
    referenceElement.parentNode.insertBefore(targetElement, referenceElement);
}
  
/**
 * Insert the target node after the reference
 * 
 * @param { Element } element to use as basis 
 * @param { Element } element that will be added
 */
export function insertElementAfter (referenceElement, targetElement) {
    referenceElement.parentNode.insertBefore(targetElement, referenceElement.nextSibling);
}

/**
 * put at the very top of the stack
 *
 * @param { Element } the DOM element
 * 
 */
 export function insertBeforeFirstChild (element) {
    const theFirstChild = element.parentNode.firstChild;
    element.parentNode.insertBefore(element, theFirstChild);
}

/**
 * Find the closest element (relative to its mid point) in a NodeList, based on a x,y of a targetPoint 
 * 
 * @param { Element }  
 * @param { NodeList }  
 * 
 * @returns { Element }   
 */
export function getClosestElement (targetElement, elements) {
    const targetPoint = getElementsLeftAndMidHeightPoint(targetElement);
    let closestElement = elements[0];
    let minDistance = 99999; //if this doesn't get overwritten we got issues...
    elements.forEach((element) => { //loop through each element and find the closest element using pythagoras theorem
        const elementsTargetPoint = getElementsLeftAndMidHeightPoint(element);
        const distance = Math.sqrt(Math.pow(elementsTargetPoint.left - targetPoint.left, 2) + Math.pow(elementsTargetPoint.top - targetPoint.top, 2));
        if (distance < minDistance) {
            minDistance = distance;
            closestElement = element;
        }
    });
    return closestElement;
};

/**
 * To get an elements left and mid height point relative to the document
 * 
 * @param { Element }  
 * 
 * @returns { x, y }   
 */
 export function getElementsLeftAndMidHeightPoint (element) {
    return {
        left: element.getBoundingClientRect().left,
        top: (element.getBoundingClientRect().top + window.scrollY) + (element.offsetHeight / 2)
    };
}

/**
 * To get an elements mid point relative to the document
 * 
 * @param { Element }  
 * 
 * @returns { x, y }   
 */
export function getElementsMidPoint (element) {
    return {
        left: element.getBoundingClientRect().left + (element.offsetWidth / 2),
        top: (element.getBoundingClientRect().top + window.scrollY) + (element.offsetHeight / 2)
    };
}

/**
 * Is the midpoint of an element above the midpoint of another element
 * 
 * @param { Element }  
 * @param { Element }  
 * 
 * @returns { Boolean }  
 */
export function isElementAboveOtherElement (firstElement, secondElement) {
    return (getElementsMidPoint(firstElement).top > getElementsMidPoint(secondElement).top);
}

/**
 * Is the midpoint of an element below the midpoint of another element
 * 
 * @param { Element }  
 * @param { Element }  
 * 
 * @returns { Boolean }  
 */
export function isElementBelowOtherElement (firstElement, secondElement) {
    return (getElementsMidPoint(firstElement).top < getElementsMidPoint(secondElement).top);
}

/**
 * Add styles to an element
 * 
 * @param { Element }  
 * @param { Styles } for example { position: 'absolute', top: '20px' } 
 */
export function setStylesOnElement (element,styles) {
    Object.assign(element.style, styles);
}

/**
 * Async inject a <script> tag into the DOM
 * https://stackoverflow.com/a/39008859
 */
export function injectScript (src) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.addEventListener('load', resolve);
        script.addEventListener('error', e => reject(e.error));
        document.head.appendChild(script);
    });
}

/**
 * Set and get an attribute on an element
 * 
 * @param { Element }  
 * @param { String }
 * @param { String }
 * 
 * @returns { String }  
 */
export function setAndGetAttribute (element, attribute, value) {
    element.setAttribute(attribute, value)
    return element.getAttribute(attribute);
}

/**
 * When new HTML is added dynamically to page, we need to ensure any <script> tags
 * have been loaded prior to adding the new HTML to the page
 * 
 * @param { Element }  note: the element shouldn't exist in the DOM yet, so we can load properly
 * 
 * @return { Element }
 */
export async function loadNewHTML (element) {
    const documentFragment = document.createRange().createContextualFragment(element.outerHTML); //createContextualFragment ensures any </script> tags within the element are run
    const scriptTags = documentFragment.querySelectorAll('script[src]'); //get each <script src></script>
    const newScriptTags = Array.from(scriptTags).filter((scriptTag) => !isScriptAdded(scriptTag.getAttribute('src'))); //only add those <scripts> that aren't already on the page
    for (const scriptTag of newScriptTags) { //add each into the document head
        await injectScript(scriptTag.getAttribute('src'));
        scriptTag.remove(); //remove each from the element as the <script> is now in the document head instead
    } 
    return documentFragment.firstElementChild;
}

/** 
 * Returns true if the script exists on the page
 * 
 * @param { String }
 * 
 * @returns { Boolean }
 */
export function isScriptAdded (src) {
    return Boolean(document.querySelector(`script[src="${src}"]`));
}