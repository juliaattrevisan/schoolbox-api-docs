/** 
 * To remove a component 
 *
 */

import { gsap } from 'gsap/all';
import { slideDown, slideUp } from '@js/util/animateUtils';
import { EXISTING_COMPONENT_SELECTOR, UPDATE_URL, getComponentID } from '../updateHomepage';
import UpdateHomepageEvents from '../updateHomepageEvents';


const ANIMATE_SPEED = 0.3;
const BUTTON_SELECTOR = '[data-target="delete"]';
const STATE_ATTRIBUTE = 'data-state'; //can be 'opened' or 'closed'
const PANEL_SELECTOR = '[data-component-panel="delete"]';

/**
 * Toggle the remove palette and manage the confirm/deny actions
 */
export function initRemoveComponents () {
    document.querySelectorAll(EXISTING_COMPONENT_SELECTOR).forEach((component) => init(component)); //existing components
    document.addEventListener(UpdateHomepageEvents.COMPONENT_ADDED, (event) => init(event.detail.component)); //newly added components
    document.addEventListener(UpdateHomepageEvents.COMPONENT_UPDATED, (event) => init(event.detail.component)); //newly added components
}

/**
 * To show/hide the confirm/cancel remove component panel
 * 
 * @param { Element } 
 */
function init (component) {
    initToggleButon(component);
    initCancelButton(component);
    initConfirmButton(component);
}   

/**
 * The show/hide button
 * 
 * @param { Element } 
 * @param { Element } 
 */
function initToggleButon (component) {
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    toggleButton.addEventListener('click', (event) => {
        event.preventDefault();
        gsap.to(toggleButton, { rotation: isOpen(component) ? 0 : 180, transformOrigin: '20px 20px', duration: ANIMATE_SPEED } );
        isOpen(component) ? close(component) : open(component);
    });
}

/**
 * Determine the state of the remove panel
 * 
 * @param { Element } 
 * 
 * @returns { Boolean }
 */
export function isOpen (component) {
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    return toggleButton.getAttribute(STATE_ATTRIBUTE) === 'opened';
}

/**
 * Open the settings panel
 * 
 * @param { Element } 
 */
function open (component) {
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    toggleButton.setAttribute(STATE_ATTRIBUTE, 'opened');
    const panel = component.querySelector(PANEL_SELECTOR);
    slideDown(panel, ANIMATE_SPEED);
}

/**
 * Close the settings panel
 * 
 * @param { Element } 
 */
async function close (component) {
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    toggleButton.setAttribute(STATE_ATTRIBUTE, 'closed');
    const panel = component.querySelector(PANEL_SELECTOR);
    await slideUp(panel, ANIMATE_SPEED);
}

/**
 * Cancel the component deletion
 * 
 * @param { Element } 
 */
function initCancelButton (component) {
    const cancelDeleteButton = component.querySelector('[data-remove="0"]');
    cancelDeleteButton.addEventListener('click', (event) => {
        event.preventDefault();
        if (isOpen(component)) {
            close(component);
        } 
    });
}

/**
 * Confirm the component deletion
 * 
 * @param { Element } 
 */
function initConfirmButton (component) {
    const confirmDeleteButton = component.querySelector('[data-remove="1"]');
    confirmDeleteButton.addEventListener('click', (event) => {
        event.preventDefault();
        removeComponent(component, confirmDeleteButton);
    });
}
 
/**
 * Save the removed component to the DB
 * 
 * @param { Element } 
 * @param { Element } 
 */
async function removeComponent (component, confirmDeleteButton) {
    try {
        document.dispatchEvent(new Event(UpdateHomepageEvents.PROCESSING_START));
        confirmDeleteButton.classList.add('disabled');
        confirmDeleteButton.innerText = 'Removing...';
        await fetch(UPDATE_URL, {
            method: 'POST',
            body: getFormData(component),
        });
        await slideUp(component);
    } catch (error) {
        console.warn(error);
        updateRemovedComponent(component);
        slideUp(component);
    } finally {
        component.remove();
        document.dispatchEvent(new CustomEvent(UpdateHomepageEvents.COMPONENT_REMOVED, { 
            detail: {
                component: component 
            }
        })); 
        document.dispatchEvent(new Event(UpdateHomepageEvents.PROCESSING_END));
    }
}

/**
 * To pass through the required FormData to remove the component in the DB
 * 
 * @param { Element } 
 * 
 * @returns { FormData }  
 */
function getFormData (component) {
    const formData = new FormData();
    formData.append('cid', document.getElementById('categoryID').value);
    formData.append('delete', getComponentID(component));
    formData.append('component', component.querySelector('[data-defn-id]').value);
    return formData;
}

/**
 * The component has been removed elsewhere, so notify the user
 * and suggest a page refresh for the latest data
 * 
 * @param { Element } 
 */
export function updateRemovedComponent (component) {

    component.querySelector('[data-component-handle]').remove(); //remove the drag trigger 
    component.querySelector('[data-component-titlebar-container] > nav').remove(); //as well as the edit controls to prevent any further interactions on this component

    const errorElement = document.createElement('div');
    errorElement.classList.add('alert', 'alert-box');
    errorElement.innerHTML = `<b>Warning:</b> This component has been removed from the page. Most likely by another user elsewhere. Please <a href="">refresh your page</a> to ensure you're up to date.`;

    const insideComponentElement = component.querySelector('[data-collapsable] > div > section');
    insideComponentElement.replaceChildren(errorElement);

    gsap.from(errorElement, { opacity: 0, duration: ANIMATE_SPEED } );

}