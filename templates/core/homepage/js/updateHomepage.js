/** 
 * Handles the majority of functionality on a Home page, including adding, sorting, and updating
 *
 */


import { initPostToMyLinks } from './postToMyLinks';
import { initExistingComponents } from './existingComponents/existingComponents';
import { initToggleLockComponents } from './existingComponents/toggleLockComponents';
import { initEditColumns } from './editColumns/editColumns';
import { initNewComponents } from './newComponents/newComponents';
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
 * To determine whether the user has full edit control, or is read-only
 * 
 * @returns { Boolean } 
 */
export function canEditHomepage () {
    return document.getElementById('component-lock-switch') ? true : false; //this element won't be on the page if is read-only
}

/**
 * Fire everything up
 */
document.addEventListener('DOMContentLoaded', () => {
    initPostToMyLinks();
    initExistingComponents();
    if (canEditHomepage()) {
        initToggleLockComponents();
        initEditColumns();
        initNewComponents();
        initUpdatePlaceHolders();
    }
});