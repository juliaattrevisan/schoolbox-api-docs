/** 
 * The buttons that sit in the new component tabs
 * To update the component counts in the new components tabs when added / removed
 * For example Text Box (0) becomes Text Box (1)
 * And also disable/enable if the component is allowed only one instance on the page
 *
 */

import UpdateHomepageEvents from '../updateHomepageEvents';
 
 
/**
 * Start listening for the relevant events
 */
export function initUpdateNewComponentButtons () {
    initOnComponentAddedListener();
    initOnComponentRemovedListener();
}

/**
 * To increment the relevant counter and check if need to disable
 */
function initOnComponentAddedListener () {
    document.addEventListener(UpdateHomepageEvents.COMPONENT_ADDED, (event) => {
        const newComponentCount = updateComponentCount(event.detail.component, true);
        checkButtonStatus(event.detail.component, newComponentCount);
    });
}

/**
 * To decrement the relevant counter and check if need to re-enable
 */
function initOnComponentRemovedListener () {
    document.addEventListener(UpdateHomepageEvents.COMPONENT_REMOVED, (event) => {
        const newComponentCount = updateComponentCount(event.detail.component, false);
        checkButtonStatus(event.detail.component, newComponentCount);
    });
}

/**
 * Update the counter element with the correct number of component type on the page
 * 
 * @param { Element }
 * @param { Boolean }
 * 
 * @returns { Number }
 */
function updateComponentCount (component, isIncrement) {
    const counterElement = getCounterElement(component);
    const currentComponentCount = parseInt(counterElement.textContent);
    const currentCount = isIncrement ? currentComponentCount + 1 : currentComponentCount - 1;
    counterElement.textContent = currentCount;
    return currentCount;
}

/**
 * Get the counter element in the palette to 
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
        if (className.includes('Component_')) { //currently assumed that every component contains this class string at its root...
            targetClassName = className;
        }
    });
    return targetClassName;
}

/**
 * Some components can only have one instance, so we have to disable further instances
 * of the component being created once one has been added
 * 
 * @param { Number }
 */
function checkButtonStatus (component, componentCount) {
    const newComponentButton = getNewComponentButton(component);
    if (componentCount === 0) {
        newComponentButton.classList.remove('disabled');
        newComponentButton.style.opacity = 1;
    } else if (newComponentButton.getAttribute('data-multi') === 'one-only') {
        newComponentButton.classList.add('disabled');
    } else {
        newComponentButton.style.opacity = 1;
    }
}

/**
 * 
 * 
 * @param { Element }
 * 
 * @returns { Element }
 */
function getNewComponentButton (component) {
    const targetClassName = getTargetClassName(component); 
    const counter = document.querySelector(`.icon-masonry-${targetClassName}`);
    const newComponentButton = counter.parentElement.parentElement;
    return newComponentButton;
}