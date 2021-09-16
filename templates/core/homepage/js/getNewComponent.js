/** 
 * Save the new component to the DB and then add into the display
 *
 */

import { UPDATE_URL, ON_COMPONENT_ADDED } from './updateHomepage';


/**
 * Submit the new component to the DB and add to display
 */
export const getNewComponent = async (component) => {
    try {
        const response = await fetch(UPDATE_URL, {
            method: 'POST',
            body: getFormData(component),
        });
        const newComponentHTML = await response.text();
        const tempElement = document.createElement('div'); //just use to insert the updated component html
        tempElement.innerHTML = newComponentHTML; //now insert it
        const newComponent = tempElement.firstElementChild; //and then extract the element
        component.replaceWith(newComponent); //switch over to the newly created component
        document.dispatchEvent(new CustomEvent(ON_COMPONENT_ADDED, { 
            detail: {
                component: newComponent 
            }
        })); 
    } catch (error) {
        console.warn(error);
    } 
}

/**
 * To pass through the required FormData to save the new component
 * 
 * @params { Element } the component that's been moved
 * 
 * @returns { FormData }  
 */
const getFormData = (component) => {
    const formData = new FormData();
    formData.append('add', component.firstElementChild.getAttribute('rel')); //this is to retrieve the new component id to know which type of component to create
    formData.append('cid', document.getElementById('categoryID').value);
    return formData;
}