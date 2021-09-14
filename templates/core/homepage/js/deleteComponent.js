/** 
 * To delete a component 
 *
 */

import { gsap } from 'gsap/all';
import axios from 'axios';
import { slideDown, slideUp } from '@js/util/animateUtils';
import { EXISTING_COMPONENT_SELECTOR, UPDATE_URL, ON_COMPONENT_REMOVED, getComponentID } from './updateHomepage';


/**
 * Toggle the delete palette and manage the confirm/deny actions
 */
export const initDeleteComponent = () => {
    document.querySelectorAll(EXISTING_COMPONENT_SELECTOR).forEach((component) => {
        const deletePanel = component.querySelector('[data-component-panel="delete"]');
        const deleteButton = component.querySelector('[data-target="delete"]');
        initDeleteComponentButton(deletePanel, deleteButton);
        initCancelDeleteComponentButton(component, deletePanel, deleteButton);
        initConfirmDeleteComponent(component);
    });
}

/**
 * To show/hide the confirm/cancel delete component panel
 * 
 * @param { Element } 
 * @param { Element } 
 */
const initDeleteComponentButton = (deletePanel, deleteButton) => {
    deleteButton.addEventListener('click', (event) => {
        event.preventDefault();
        if (deleteButton.getAttribute('data-state') === 'closed') {
            deleteButton.setAttribute('data-state', 'opened');
            gsap.to(deleteButton, { rotation: '+=180', transformOrigin: '20px 20px', duration: 0.5 } );
            slideDown(deletePanel, 0.5);
        } else {
            deleteButton.setAttribute('data-state', 'closed');
            gsap.to(deleteButton, { rotation: '+=180', transformOrigin: '20px 20px', duration: 0.5 } );
            slideUp(deletePanel, 0.5);
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
const initCancelDeleteComponentButton = (component, deletePanel, deleteButton) => {
    const cancelDeleteButton = component.querySelector('[data-remove="0"]');
    cancelDeleteButton.addEventListener('click', (event) => {
        event.preventDefault();
        if (deleteButton.getAttribute('data-state') === 'opened') {
            deleteButton.setAttribute('data-state', 'closed');
            slideUp(deletePanel, 0.5);
        } 
    });
}

/**
 * Confirm the component deletion
 * 
 * @param { Element } 
 */
const initConfirmDeleteComponent = (component) => {
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
const deleteComponent = async (component) => {
    try {
        await axios.post(UPDATE_URL, getDeleteComponentFormData(component));
        await slideUp(component);
        // if it was the last component in a column, reshow the placeholder
        document.dispatchEvent(new CustomEvent(ON_COMPONENT_REMOVED, { //dispatch to allow sortableComponents.js to remove from sortable list
            detail: {
                componentID: getComponentID(component) //id will be used to remove the sortable
            }
        })); 
        component.remove();
    } catch (error) {
        console.warn(error);
    } 
}

/**
 * To pass through the required FormData to delete the component in the DB
 * 
 * @param { Element } 
 * 
 * @returns { FormData }  
 */
const getDeleteComponentFormData = (component) => {
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
export const updateDeletedComponent = (component) => {

    component.querySelector('[data-component-handle]').remove(); //remove the drag trigger 
    component.querySelector('[data-component-titlebar-container] > nav').remove(); //as well as the edit controls to prevent any further interactions on this component

    const errorElement = document.createElement('div');
    errorElement.classList.add('alert', 'alert-box');
    errorElement.innerHTML = `<b>Warning:</b> This component has been deleted from the page. Most likely by another user elsewhere. Please <a href="">refresh your page</a> to ensure you're up to date.`;

    const insideComponentElement = component.querySelector('[data-collapsable] > div > section');
    insideComponentElement.replaceChildren(errorElement);

    gsap.from(errorElement, { opacity: 0, duration: 0.5 } );

}