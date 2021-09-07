/** 
 * Save the new sorting order, as a component has been moved to a new spot
 *
 */

import { gsap } from 'gsap/all';
import axios from 'axios';
import { COMPONENT_SELECTOR } from './updateHomepage';


/**
 * Submit the new component order to the DB
 */
export const saveComponentOrder = async (activeComponent) => {
    try {
        const response = await axios.post('/homepage/configForm.php', getNewComponentOrderFormData(activeComponent));
        if (response.data.hasComponentBeenDeleted) {
            updateDeletedComponent();
        }
    } catch (error) {
        console.warn(error);
    } 
}

/**
 * To pass through the required FormData to save the new position in the DB
 * 
 * @params { DOMElement } the component that's been moved
 * 
 * @returns { FormData }  
 */
const getNewComponentOrderFormData = (activeComponent) => {
    const formData = new FormData();
    formData.append('column1', getContainerComponentIDs(document.querySelector('[data-position="left"]'))); //left side panel
    formData.append('column2', getContainerComponentIDs(document.querySelector('[data-position="right"]'))); //right side panel
    formData.append('column3', getContainerComponentIDs(document.querySelector('[data-position="top"]'))); //top panel... I know right?!
    formData.append('movedComponentId', getComponentID(activeComponent));
    formData.append('type', 'components');
    formData.append('cid', document.getElementById('categoryID').value);
    return formData;
}

/**
 * To retrieve all component ids within a container
 * 
 * @param { DOMElement } 
 * 
 * @returns { String } 
 */
const getContainerComponentIDs = (componentContainer) => {
    let containerComponentIDList = []; //add all ids into an array
    componentContainer.querySelectorAll(COMPONENT_SELECTOR).forEach((sortable) => {
        if (sortable.id) { //the <div class="masonry-placeholder" /> exists in each parent container, so we don't want that
            containerComponentIDList.push(getComponentID(sortable));
        }
    });
    return containerComponentIDList.join(','); //return just a string or the component ids with a comma in between each
}

/**
 * To retrieve the component id
 * 
 * @param { DOMElement } 
 * 
 * @returns { String } 
 */
const getComponentID = (componentElement) => {
    return componentElement.id.replace('component', ''); //strip the 'component' at the start to just get the id number
}

/**
 * The component has been deleted elsewhere, so notify the user
 * and suggest a page refresh for the latest data
 */
const updateDeletedComponent = () => {

    __activeComponent.querySelector(COMPONENT_TRIGGER_SELECTOR).remove(); //remove the drag trigger 
    __activeComponent.querySelector('[data-component-titlebar-container] > nav').remove(); //as well as the edit controls to prevent any further interactions on this component

    const errorElement = document.createElement('div');
    errorElement.classList.add('alert', 'alert-box');
    errorElement.innerHTML = `<b>Warning:</b> This component has been deleted from the page. Most likely by another user elsewhere. Please <a href="">refresh your page</a> to ensure you're up to date.`;

    const insideComponentElement = __activeComponent.querySelector('[data-collapsable] > div > section');
    insideComponentElement.replaceChildren(errorElement);

    gsap.from(errorElement, { opacity: 0, duration: 0.5 } );

}