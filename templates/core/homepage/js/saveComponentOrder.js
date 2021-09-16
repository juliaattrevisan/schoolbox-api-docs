/** 
 * Save the new sorting order, as a component has been moved to a new spot
 *
 */

import { EXISTING_COMPONENT_SELECTOR, UPDATE_URL, getComponentID } from './updateHomepage';
import { updateDeletedComponent } from './deleteComponents';


/**
 * Submit the new component order to the DB
 */
export const saveComponentOrder = async (component) => {
    try {
        const response = await fetch(UPDATE_URL, {
            method: 'POST',
            body: getFormData(component),
        });
        const responseData = await response.json();
        if (responseData.hasComponentBeenDeleted) {
            updateDeletedComponent(component);
        }
    } catch (error) {
        console.warn(error);
    } 
}

/**
 * To pass through the required FormData to save the new position in the DB
 * 
 * @params { Element } the component that's been moved
 * 
 * @returns { FormData }  
 */
const getFormData = (component) => {
    const formData = new FormData();
    formData.append('column1', getContainerComponentIDs(document.querySelector('[data-position="left"]'))); //left side panel
    formData.append('column2', getContainerComponentIDs(document.querySelector('[data-position="right"]'))); //right side panel
    formData.append('column3', getContainerComponentIDs(document.querySelector('[data-position="top"]'))); //top panel... I know right?!
    formData.append('movedComponentId', getComponentID(component));
    formData.append('type', 'components');
    formData.append('cid', document.getElementById('categoryID').value);
    return formData;
}

/**
 * To retrieve all component ids within a container
 * 
 * @param { Element } 
 * 
 * @returns { String } 
 */
const getContainerComponentIDs = (componentContainer) => {
    let containerComponentIDList = []; //add all ids into an array
    componentContainer.querySelectorAll(EXISTING_COMPONENT_SELECTOR).forEach((sortable) => {
        if (sortable.id) { //the <div class="masonry-placeholder" /> exists in each parent container, so we don't want that
            containerComponentIDList.push(getComponentID(sortable));
        }
    });
    return containerComponentIDList.join(','); //return just a string or the component ids with a comma in between each
}