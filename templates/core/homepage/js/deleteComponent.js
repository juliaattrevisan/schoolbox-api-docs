/** 
 * 
 *
 */

import { gsap } from 'gsap/all';
import axios from 'axios';
import { slideDown, slideUp } from '@js/util/animateUtils';

/**
 * 
 */
export const initDeleteComponent = () => {
    document.querySelectorAll('[data-component]').forEach((component) => {
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
 * @param { DOMElement } 
 * @param { DOMElement } 
 * @param { DOMElement } 
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
 * @param { DOMElement } 
 * @param { DOMElement } 
 * @param { DOMElement } 
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
 * @param { DOMElement } 
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
 * @param { DOMElement } 
 */
const deleteComponent = async (component) => {
    try {
        await axios.post('/homepage/configForm.php', getDeleteComponentFormData(component));
        await slideUp(component);
        // if it was the last component in a column, reshow the placeholder
        document.dispatchEvent(new CustomEvent('jquery.masonry__onComponentRemoved', { //dispatch to allow sortableComponents.js to remove from sortable list
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
 * @param { DOMElement } 
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
 * To retrieve the component id
 * 
 * @param { DOMElement } 
 * 
 * @returns { String } 
 */

const getComponentID = (component) => {
    return component.id.replace('component', ''); //strip the 'component' at the start to just get the id number
}