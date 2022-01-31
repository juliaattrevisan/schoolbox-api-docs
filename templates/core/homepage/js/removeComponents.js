/** 
 * To delete a component 
 *
 */

import { gsap } from 'gsap/all';
import { slideDown, slideUp } from '@js/util/animateUtils';
import { EXISTING_COMPONENT_SELECTOR, UPDATE_URL, ON_COMPONENT_ADDED, ON_COMPONENT_REMOVED, getComponentID } from './updateHomepage';

const ANIMATE_SPEED = 0.4;


/**
 * Toggle the delete palette and manage the confirm/deny actions
 */
export function initDeleteComponents () {
    document.querySelectorAll(EXISTING_COMPONENT_SELECTOR).forEach((component) => init(component)); //existing components
    document.addEventListener(ON_COMPONENT_ADDED, (event) => init(event.detail.component)); //newly added components
}

/**
 * To show/hide the confirm/cancel delete component panel
 * 
 * @param { Element } 
 */
function init (component) {
    const panel = component.querySelector('[data-component-panel="delete"]');
    const toggleButton = component.querySelector('[data-target="delete"]');
    initToggleButon(panel, toggleButton);
    initCancelButton(component, panel, toggleButton);
    initConfirmButton(component);
}   

/**
 * The show/hide button
 * 
 * @param { Element } 
 * @param { Element } 
 */
function initToggleButon (panel, toggleButton) {
    toggleButton.addEventListener('click', (event) => {
        event.preventDefault();
        if (toggleButton.getAttribute('data-state') === 'closed') {
            toggleButton.setAttribute('data-state', 'opened');
            gsap.to(toggleButton, { rotation: '+=180', transformOrigin: '20px 20px', duration: ANIMATE_SPEED } );
            slideDown(panel, ANIMATE_SPEED);
        } else {
            toggleButton.setAttribute('data-state', 'closed');
            gsap.to(toggleButton, { rotation: '+=180', transformOrigin: '20px 20px', duration: ANIMATE_SPEED } );
            slideUp(panel, ANIMATE_SPEED);
        }
    });
}

/**
 * Cancel the component deletion
 * 
 * @param { Element } 
 * @param { Element } 
 * @param { Element } 
 */
function initCancelButton (component, panel, toggleButton) {
    const cancelDeleteButton = component.querySelector('[data-remove="0"]');
    cancelDeleteButton.addEventListener('click', (event) => {
        event.preventDefault();
        if (toggleButton.getAttribute('data-state') === 'opened') {
            toggleButton.setAttribute('data-state', 'closed');
            slideUp(panel, ANIMATE_SPEED);
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
export function updateDeletedComponent (component) {

    component.querySelector('[data-component-handle]').remove(); //remove the drag trigger 
    component.querySelector('[data-component-titlebar-container] > nav').remove(); //as well as the edit controls to prevent any further interactions on this component

    const errorElement = document.createElement('div');
    errorElement.classList.add('alert', 'alert-box');
    errorElement.innerHTML = `<b>Warning:</b> This component has been deleted from the page. Most likely by another user elsewhere. Please <a href="">refresh your page</a> to ensure you're up to date.`;

    const insideComponentElement = component.querySelector('[data-collapsable] > div > section');
    insideComponentElement.replaceChildren(errorElement);

    gsap.from(errorElement, { opacity: 0, duration: ANIMATE_SPEED } );

}