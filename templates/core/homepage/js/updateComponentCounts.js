/** 
 * To update the component counts in the palette when added / removed
 * For example Text Box (0) becomes Text Box (1)
 *
 */

 import UpdateHomepageEvents from './updateHomepageEvents';
 
 
/**
 * Toggle the delete palette and manage the confirm/deny actions
 */
export function initUpdateComponentCounts () {
    initOnComponentAddedListener();
    initOnComponentRemovedListener();
}

/**
 * To increment the relevant counter
 */
function initOnComponentAddedListener () {
    document.addEventListener(UpdateHomepageEvents.ON_COMPONENT_ADDED, (event) => {
        const counterElement = getCounterElement(event.detail.component);
        counterElement.textContent = parseInt(counterElement.textContent) + 1;
    });
}

/**
 * To decrement the relevant counter
 */
function initOnComponentRemovedListener () {
    document.addEventListener(UpdateHomepageEvents.ON_COMPONENT_REMOVED, (event) => {
        const counterElement = getCounterElement(event.detail.component);
        counterElement.textContent = parseInt(counterElement.textContent) - 1;
    });
}

/**
 * Get the counter element in the palette to increment / decrmment the number inside 
 * 
 * @param { Element }
 * 
 * @returns { Element }
 */
function getCounterElement (component) {
    const targetClassName = getTargetClassName(component); 
    const counter = document.querySelector(`.icon-masonry-${targetClassName}`);
    const counterElement = counter.parentElement.querySelector('[data-relation="count"]');
    return counterElement;
}

/**
 * To select the appropriate item in the palette
 * @todo - Can we change from targeting a class? 
 * 
 * @param { Element }
 */
function getTargetClassName (component) {
    let targetClassName;
    component.classList.forEach((className) => {
        if (className.includes('_Component_Homepage')) { //currently assumed that every component contains this class at its root...
            targetClassName = className;
        }
    });
    return targetClassName;
}