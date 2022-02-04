/** 
 * Save the new component to the DB and then add into the display
 *
 */

import { UPDATE_URL } from '../updateHomepage';
import { updateComponentDisplay, isToggleUnlocked } from '../existingComponents/toggleLockComponents';


/**
 * Submit the new component to the DB and add to display
 */
export async function getNewComponent (component) {
    try {
        const response = await fetch(UPDATE_URL, {
            method: 'POST',
            body: getFormData(component),
        });
        const newComponentHTML = await response.text();
        const newComponent = replaceExistingComponent(component, newComponentHTML);
        return newComponent;
    } catch (error) {
        console.warn(error);
    } 
}

/**
 * Update the just added component to the actual component that's been loaded in
 * @todo can this be handled better?
 * 
 * @params { String } the component that's been moved
 * 
 * @returns { Element }  
 */
function replaceExistingComponent (component, newComponentHTML) {
    const tempElement = document.createElement('div'); //just use to insert the updated component html
    tempElement.innerHTML = newComponentHTML; //now insert it
    const newComponent = tempElement.firstElementChild; //and then extract the element
    component.replaceWith(newComponent); //switch over to the newly created component
    updateComponentDisplay(newComponent, isToggleUnlocked()) //ensure the new component respects the lock status of the page
    return newComponent;
}

/**
 * To pass through the required FormData to save the new component
 * 
 * @params { Element } the component that's been moved
 * 
 * @returns { FormData }  
 */
function getFormData (component) {
    const formData = new FormData();
    formData.append('add', component.querySelector('a').getAttribute('rel')); //this is to retrieve the new component id to know which type of component to create
    formData.append('cid', document.getElementById('categoryID').value);
    return formData;
}