/** 
 * Handles the majority of functionality on a Home page, including adding, sorting, and updating
 *
 */


import { initToggleLockComponents } from './existingComponents/toggleLockComponents';
import { initPostToMyLinks } from './postToMyLinks';
import { initEditColumns } from './editColumns/editColumns';
import { initNewComponents } from './newComponents/newComponents';
import { initExistingComponents } from './existingComponents/existingComponents';
import { initUpdatePlaceHolders } from './updatePlaceHolders';
  
export const EXISTING_COMPONENT_SELECTOR = '[data-component]'; 
export const UPDATE_URL = '/homepage/configForm.php'; 


/**
 * To retrieve the component id
 * 
 * @param { Element } 
 * 
 * @returns { String } 
 */
export function getComponentID (component) {
    return component.id.replace('component', ''); //strip the 'component' at the start to just get the id number
}

/**
 * Fire everything up
 */
document.addEventListener('DOMContentLoaded', () => {
    initToggleLockComponents();
    initPostToMyLinks();
    initEditColumns();
    initNewComponents();
    initExistingComponents();
    initUpdatePlaceHolders();
});