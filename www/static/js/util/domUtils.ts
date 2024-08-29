/*
 * Basic DOM manipulation utilities
 */

import { escape } from "lodash";
import { escapeRegExp } from "@js/util/jsUtils";

/**
 * returns an integer indicating the position of the
 * passed element relative to the original collection
 * replacement for $(el).index
 * @param {HTMLElement} element - the DOM element to find the index of
 * @returns {number} - position of the DOM element
 */
export function getIndex(element: HTMLElement): number {
    if (!element || !element.parentElement) {
        return -1;
    }
    return [...element.parentElement.children].indexOf(element);
}

/**
 * remove all targeted elements from a container
 * @param {HTMLElement} element - the DOM element to display none all children
 * @param {string} selector - the selector to use to find the children
 */
export function removeAllFromElement(element: HTMLElement, selector: string) {
    const elements = element.querySelectorAll(selector);
    Array.from(elements).forEach((elementItem) => {
        elementItem.remove();
    });
}

/**
 * display none all children
 * @param {HTMLElement} element - the DOM element to display: none all children
 */
export function hideAllChildren(element: HTMLElement) {
    Array.from(element.children).forEach((elementItem) => {
        (elementItem as HTMLDivElement).style.display = "none";
    });
}

/**
 * empty the element of children
 * replacement for $(el).empty
 * @param {HTMLElement} element - the DOM element to empty
 */
export function empty(element: HTMLElement) {
    while (element.firstChild) {
        element.removeChild(element.firstChild);
    }
}

/**
 * switch elements, changing stacking order
 * @param {HTMLElement} firstElement - the first DOM element
 * @param {HTMLElement} secondElement - the second DOM element
 */
export function swapElements(firstElement: HTMLElement, secondElement: HTMLElement) {
    firstElement.parentNode?.insertBefore(firstElement, secondElement);
}

/**
 * Insert the target node before the reference
 * @param {HTMLElement} referenceElement - element to use as basis
 * @param {HTMLElement} targetElement - element that will be added
 */
export function insertElementBefore(referenceElement: HTMLElement, targetElement: HTMLElement) {
    referenceElement.parentNode?.insertBefore(targetElement, referenceElement);
}

/**
 * Insert the target node after the reference
 * @param {HTMLElement} referenceElement - element to use as basis
 * @param {HTMLElement} targetElement - element that will be added
 */
export function insertElementAfter(referenceElement: HTMLElement, targetElement: HTMLElement) {
    referenceElement.parentNode?.insertBefore(targetElement, referenceElement.nextSibling);
}

/**
 * put at the very top of the stack
 * @param {HTMLElement} element - the DOM element
 */
export function insertBeforeFirstChild(element: HTMLElement) {
    const theFirstChild = element.parentNode?.firstChild as ChildNode;
    element.parentNode?.insertBefore(element, theFirstChild);
}

/**
 * To get an elements left and mid height point relative to the document
 * @param {HTMLElement} element - the element to get the left and mid height point of
 * @returns {object} - the left and mid height point of the element
 */
export function getElementsLeftAndMidHeightPoint(element: HTMLElement): { left: number; top: number } {
    return {
        left: element.getBoundingClientRect().left,
        top: element.getBoundingClientRect().top + window.scrollY + element.offsetHeight / 2,
    };
}

/**
 * Find the closest element (relative to its mid point) in a NodeList, based on a x,y of a targetPoint
 * @param {HTMLElement} targetElement - the element to find the closest element to
 * @param {HTMLElement[]} elements - the elements to search through
 * @returns {HTMLElement} - the closest element
 */
export function getClosestElement(targetElement: HTMLElement, elements: HTMLElement[]): HTMLElement {
    const targetPoint = getElementsLeftAndMidHeightPoint(targetElement);
    let closestElement = elements[0];
    let minDistance = 99999; // if this doesn't get overwritten we got issues...
    elements.forEach((element) => {
        // loop through each element and find the closest element using pythagoras theorem
        const elementsTargetPoint = getElementsLeftAndMidHeightPoint(element);
        // ** is: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Exponentiation
        const distance = Math.sqrt(
            (elementsTargetPoint.left - targetPoint.left) ** 2 + (elementsTargetPoint.top - targetPoint.top) ** 2,
        );
        if (distance < minDistance) {
            minDistance = distance;
            closestElement = element;
        }
    });
    return closestElement;
}

/**
 * To get an elements mid point relative to the document
 * @param {HTMLElement} element - the element to get the mid point of
 * @returns {object} - the mid point of the element
 */
export function getElementsMidPoint(element: HTMLElement): { left: number; top: number } {
    return {
        left: element.getBoundingClientRect().left + element.offsetWidth / 2,
        top: element.getBoundingClientRect().top + window.scrollY + element.offsetHeight / 2,
    };
}

/**
 * Is the midpoint of an element above the midpoint of another element
 * @param {HTMLElement} firstElement - the element to check
 * @param {HTMLElement} secondElement - the element to check against
 * @returns {boolean} - true if the first element is above the second element
 */
export function isElementAboveOtherElement(firstElement: HTMLElement, secondElement: HTMLElement): boolean {
    return getElementsMidPoint(firstElement).top > getElementsMidPoint(secondElement).top;
}

/**
 * Is the midpoint of an element below the midpoint of another element
 * @param {HTMLElement} firstElement - the element to check
 * @param {HTMLElement} secondElement - the element to check against
 * @returns {boolean} - true if the first element is below the second element
 */
export function isElementBelowOtherElement(firstElement: HTMLElement, secondElement: HTMLElement): boolean {
    return getElementsMidPoint(firstElement).top < getElementsMidPoint(secondElement).top;
}

/**
 * Add styles to an element
 * @param {HTMLElement} element - the element to add styles to
 * @param {object} styles - for example { position: 'absolute', top: '20px' }
 */
export function setStylesOnElement(element: HTMLElement, styles: object): void {
    Object.assign(element.style, styles);
}

/**
 * Set and get an attribute on an element
 * @param {HTMLElement} element - the element to set the attribute on
 * @param {string} attribute - the attribute to set
 * @param {string} value - the value to set the attribute to
 * @returns {string | null} - the value of the attribute
 */
export function setAndGetAttribute(element: HTMLElement, attribute: string, value: string): string | null {
    element.setAttribute(attribute, value);
    return element.getAttribute(attribute);
}

/**
 * Returns the script element
 * @param {string} src - the src of the script
 * @returns { HTMLScriptElement | null } - the script element, or null if it doesn't exist
 */
export function getScriptElement(src: string): HTMLScriptElement | null {
    return document.querySelector(`script[src="${src}"]`);
}

/**
 * Returns true if the script exists on the page
 * @param {string} src - the src of the script
 * @returns {boolean} - true if the script exists on the page
 */
export function isScriptAdded(src: string): boolean {
    return Boolean(getScriptElement(src));
}

/**
 * Returns true if the script has been loaded
 * @param {string} src - the src of the script
 * @returns {boolean} - true if the script has been loaded
 */
export function isScriptLoaded(src: string): boolean {
    const scriptElement = getScriptElement(src);
    return Boolean(scriptElement && scriptElement.getAttribute("data-is-loaded") === "true");
}

/**
 * Returns true if the script was already injected
 * @param {string} src - the src of the script
 * @returns {boolean} - true if the script was already injected
 */
export function isScriptInjected(src: string): boolean {
    // The script must have a data-is-loaded attribute to be considered injected
    const scriptElement = getScriptElement(src);
    return Boolean(scriptElement && scriptElement.getAttribute("data-is-loaded") !== null);
}

/**
 * Async inject a <script> tag into the DOM
 * https://stackoverflow.com/a/39008859
 * @param {string} src - the src of the script to inject
 */
export function injectScript(src: string): Promise<void> {
    return new Promise((resolve, reject) => {
        // Have we already injected this script?
        if (isScriptInjected(src)) {
            // Has it already loaded?
            if (isScriptLoaded(src)) {
                resolve();
            } else {
                // If not, then wait for it to load.
                //  Noting that other event listeners were already added,
                //  so we don't need to do the same logic if we just created it.
                const script = getScriptElement(src)!;
                script.addEventListener("load", () => {
                    resolve();
                });
                script.addEventListener("error", (e) => {
                    reject(e.error);
                });
            }

            return;
        }

        // Otherwise, inject the script
        const script = document.createElement("script");
        script.setAttribute("data-is-loaded", "false");
        script.addEventListener("load", () => {
            script.setAttribute("data-is-loaded", "true");
            resolve();
        });
        script.addEventListener("error", (e) => {
            console.error("injectScript failed for file ", src);
            reject(e.error);
        });
        script.src = src;
        document.head.appendChild(script);
    });
}

/**
 * When new HTML is added dynamically to page, we need to ensure any <script> tags
 * have been loaded prior to adding the new HTML to the page
 * @param {string} htmlString - note: the HTML shouldn't exist in the DOM yet, so we can load properly
 * @returns { Element | null } - the HTML as a DOM element, or null if it couldn't be loaded
 */
export async function getLoadedHTML(htmlString: string): Promise<Element | null> {
    const documentFragment = document.createRange().createContextualFragment(htmlString); // createContextualFragment ensures any </script> tags within the element are run
    const scriptTags = documentFragment.querySelectorAll("script[src]:not([data-is-vue])"); // get each <script src></script> that isn't a vue component (which don't require preloading)
    const newScriptTags = Array.from(scriptTags).filter(
        (scriptTag) => !isScriptAdded(scriptTag.getAttribute("src") as string),
    ); // only add those <scripts> that aren't already on the page
    const injections: Promise<void>[] = [];
    newScriptTags.forEach((scriptTag) => {
        injections.push(injectScript(scriptTag.getAttribute("src") as string));
    });
    // add each into the document head
    await Promise.all(injections);
    if (documentFragment.childElementCount === 1) {
        // should be returning a single div
        return documentFragment.firstElementChild;
    }
    // and if there's not, wrap everything around one
    const wrappedElement = document.createElement("div");
    wrappedElement.append(documentFragment);
    return wrappedElement;
}

type InjectScriptsParams = {
    srcList: string[];
    onComplete?: () => void;
};

/**
 * Inject a list of scripts into the DOM (skipping those that are already present), then run the given function on completion.
 * @param {InjectScriptsParams} params - The list of scripts to inject.
 */
export async function injectScriptsIfNotLoaded(params: InjectScriptsParams) {
    // Scripts are injected sequentially here because often sets of scripts need to be added in a particular order.
    // Example: jstree.min.js must be added before jQuery.resourceTree.js.
    await Promise.all(
        params.srcList.map(async (src) => {
            if (!isScriptLoaded(src)) {
                await injectScript(src);
            }
        }),
    );

    if (params.onComplete !== undefined) {
        params.onComplete();
    }
}

/**
 * To add <mark> elements around the input on any of the matches
 * (?![^>]*>) is used to check we are not inside an element node
 * @param {string} toMark - the string to find all instanced of the current search string in
 * @param {string[]} wordList - the list of words to search for
 * @returns {string} string for v-html with <mark></mark> wrapped around each found result:
 *                          any HTML special characters in `toMark` are escaped,
 *                          so output is known to be safe for v-html
 */
export function addMarks(toMark: string, wordList: string[]): string {
    let marked = escape(toMark);
    const uniqueWorkList = [...new Set(wordList)]; // remove any duplicate search words
    uniqueWorkList.forEach((wordInSearch) => {
        const regex = new RegExp(`(?<!<[^>]*)\\b${escapeRegExp(wordInSearch)}\\b(?![^<]*>)`, "ig");
        marked = marked.replace(regex, (match) => {
            return `<mark>${match}</mark>`;
        });
    });
    return marked;
}
