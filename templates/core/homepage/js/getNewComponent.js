/** 
 * 
 *
 */

import axios from 'axios';


/**
 * Submit the new component order to the DB
 */
export const getNewComponent = async (activeComponent) => {
    try {
        const response = await axios.post('/homepage/configForm.php', getNewComponentOrderFormData(activeComponent));
        const newComponent = response.data;
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = newComponent;
        activeComponent.replaceWith(tempDiv.firstChild);

        //determine whether the checkbox edit is active
        //notify of added

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
    formData.append('add', '51');
    formData.append('cid', document.getElementById('categoryID').value);
    return formData;
}