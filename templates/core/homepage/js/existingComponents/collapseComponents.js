/** 
 * Manange a commponents ability to open and close
 *
 */

import { gsap } from 'gsap/all';
import { slideDown, slideUp } from '@js/util/animateUtils';
import { EXISTING_COMPONENT_SELECTOR } from '../updateHomepage';
import UpdateHomepageEvents from '../updateHomepageEvents';

const PANEL_SELECTOR = '[data-collapsable="true"]';
const BUTTON_SELECTOR = '[data-collapser="true"]';
const ANIMATE_SPEED = 0.4; //the speed of animations


/**
 * Set up all components and listen for newly added ones
 */
export function initCollapseComponents () {
    document.querySelectorAll(EXISTING_COMPONENT_SELECTOR).forEach((component) => init(component));
    document.addEventListener(UpdateHomepageEvents.COMPONENT_ADDED, (event) => init(event.detail.component));
    document.addEventListener(UpdateHomepageEvents.COMPONENT_UPDATED, (event) => init(event.detail.component));
}

/**
 * Open and close a component
 * 
 * @param { Element } 
 */
function init (component) {
    const collapseButton = component.querySelector(BUTTON_SELECTOR);
    if (collapseButton) { //not all components have a collapse button to access
        collapseButton.addEventListener('click', (event) => {
            event.preventDefault();
            isOpen(component) ? close(component) : open(component);
        });
    }
} 

/**
 * Check to determine components collapse state
 * 
 * @params { Element }
 * 
 * @returns { Boolean }
 */
export function isOpen (component) {
    const collapsePanel = component.querySelector(PANEL_SELECTOR);
    return collapsePanel.getAttribute('data-collapse-state') === 'uncollapsed';
}

/**
 * Uncollapse a component, sliding the content within down
 * 
 * @params { Element }
 */
export async function open (component) {
    const collapseButton = component.querySelector(BUTTON_SELECTOR);
    gsap.to(collapseButton, { rotation: 0, transformOrigin: '20px 20px', duration: ANIMATE_SPEED } );
    const collapsePanel = component.querySelector(PANEL_SELECTOR);
    collapsePanel.setAttribute('data-collapse-state', 'uncollapsed');
    await slideDown(collapsePanel, ANIMATE_SPEED);
}

/**
 * Collapse a component, sliding the content within up
 * 
 * @params { Element }
 */
export async function close (component) {
    const collapseButton = component.querySelector(BUTTON_SELECTOR);
    gsap.to(collapseButton, { rotation: 180, transformOrigin: '20px 20px', duration: ANIMATE_SPEED } );
    const collapsePanel = component.querySelector(PANEL_SELECTOR);
    collapsePanel.setAttribute('data-collapse-state', 'collapsed');
    await slideUp(collapsePanel, ANIMATE_SPEED);
}

/**
 * Check the state of the 'Collapse Content' checkbox to determine display
 * 
 * @params { Element }
 * 
 * @returns { Boolean }
 */
export function isCollapseComponentSelected (component) {
    const collapseCheckbox = component.querySelector(`#${component.id}-collapse`);
    return collapseCheckbox.getAttribute('checked') === 'checked' ? true : false;
}
 