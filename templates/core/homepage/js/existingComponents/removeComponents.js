/** 
 * To delete a component 
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
 * Toggle the delete palette and manage the confirm/deny actions
 */
export function initRemoveComponents () {
    document.querySelectorAll(EXISTING_COMPONENT_SELECTOR).forEach((component) => init(component)); //existing components
    document.addEventListener(UpdateHomepageEvents.ON_COMPONENT_ADDED, (event) => init(event.detail.component)); //newly added components
}

/**
 * To show/hide the confirm/cancel delete component panel
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
        gsap.to(toggleButton, { rotation: '+=180', transformOrigin: '20px 20px', duration: ANIMATE_SPEED } );
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
    const panel = component.querySelector(PANEL_SELECTOR);
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    toggleButton.setAttribute(STATE_ATTRIBUTE, 'opened');
    slideDown(panel, ANIMATE_SPEED);
}

/**
 * Close the settings panel
 * 
 * @param { Element } 
 */
async function close (component) {
    const panel = component.querySelector(PANEL_SELECTOR);
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    toggleButton.setAttribute(STATE_ATTRIBUTE, 'closed');
    await slideUp(panel, ANIMATE_SPEED);
}

/**
 * Cancel the component deletion
 * 
 * @param { Element } 
 * @param { Element } 
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
    const comfirmDeleteButton = component.querySelector('[data-remove="1"]');
    comfirmDeleteButton.addEventListener('click', (event) => {
        event.preventDefault();
        deleteComponent(component);
    });
}
 
/**
 * Save the deleted component to the
 * 
 * @param { Element } 
 */
async function deleteComponent (component) {
    try {
        await fetch(UPDATE_URL, {
            method: 'POST',
            body: getFormData(component),
        });
        await slideUp(component);
    } catch (error) {
        console.warn(error);
    } finally {
        document.dispatchEvent(new CustomEvent(ON_COMPONENT_REMOVED, { //dispatch to allow removing from the sortable list in sortExistingComponents.js
            detail: {
                component: component 
            }
        })); 
        component.remove();
    }
}

/**
 * To pass through the required FormData to delete the component in the DB
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
 * The component has been deleted elsewhere, so notify the user
 * and suggest a page refresh for the latest data
 * 
 * @param { Element } 
 */
export function updateRemovedComponent (component) {

    component.querySelector('[data-component-handle]').remove(); //remove the drag trigger 
    component.querySelector('[data-component-titlebar-container] > nav').remove(); //as well as the edit controls to prevent any further interactions on this component

    const errorElement = document.createElement('div');
    errorElement.classList.add('alert', 'alert-box');
    errorElement.innerHTML = `<b>Warning:</b> This component has been deleted from the page. Most likely by another user elsewhere. Please <a href="">refresh your page</a> to ensure you're up to date.`;

    const insideComponentElement = component.querySelector('[data-collapsable] > div > section');
    insideComponentElement.replaceChildren(errorElement);

    gsap.from(errorElement, { opacity: 0, duration: ANIMATE_SPEED } );

}