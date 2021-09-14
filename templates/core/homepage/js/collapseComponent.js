/** 
 * Manange a commponents ability to open and close
 *
 */

import { gsap } from 'gsap/all';
import { slideDown, slideUp } from '@js/util/animateUtils';

const COLLAPSABLE_SELECTOR = '[data-collapsable="true"]';
const COLLAPSE_BUTTON_SELECTOR = '[data-collapser="true"]';


/**
 * 
 */
 export const initCollapseComponent = () => {
    document.querySelectorAll('[data-component]').forEach((component) => {
        const collapseButton = component.querySelector(COLLAPSE_BUTTON_SELECTOR);
        collapseButton.addEventListener('click', (event) => {
            event.preventDefault();
            isComponentUncollapsed(component) ? collapseComponent(component) : uncollapseComponent(component);
        });
    });
}

/**
 * Check to determine components collapse state
 * 
 * @params { Element }
 * 
 * @returns { Boolean }
 */
export const isComponentUncollapsed = (component) => {
    const collapsePanel = component.querySelector(COLLAPSABLE_SELECTOR);
    return collapsePanel.getAttribute('data-collapse-state') === 'uncollapsed';
}

/**
 * Unollapse a component, sliding the content within down
 * 
 * @params { Element }
 */
export const uncollapseComponent = async (component) => {
    const collapseButton = component.querySelector(COLLAPSE_BUTTON_SELECTOR);
    gsap.to(collapseButton, { rotation: '+=180', transformOrigin: '20px 20px', duration: 0.5 } );
    const collapsePanel = component.querySelector(COLLAPSABLE_SELECTOR);
    collapsePanel.setAttribute('data-collapse-state', 'uncollapsed');
    await slideDown(collapsePanel, 0.5);
}

/**
 * Collapse a component, sliding the content within up
 * 
 * @params { Element }
 */
export const collapseComponent = async (component) => {
    const collapseButton = component.querySelector(COLLAPSE_BUTTON_SELECTOR);
    gsap.to(collapseButton, { rotation: '+=180', transformOrigin: '20px 20px', duration: 0.5 } );
    const collapsePanel = component.querySelector(COLLAPSABLE_SELECTOR);
    collapsePanel.setAttribute('data-collapse-state', 'collapsed');
    await slideUp(collapsePanel, 0.5);
}

/**
 * Check the state of the 'Collapse Content' checkbox to determine display
 * 
 * @params { Element }
 * 
 * @returns { Boolean }
 */
export const isCollapseComponentSelected = (component) => {
    const collapseCheckbox = component.querySelector(`#${component.id}-collapse`);
    return collapseCheckbox.getAttribute('checked') === 'checked' ? true : false;
}
 