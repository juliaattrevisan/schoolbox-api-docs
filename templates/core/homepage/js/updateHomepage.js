/** 
 * Handles the majority of functionality on a Home page, including adding, sorting, and updating
 *
 */


import { initToggleLockComponents } from './existingComponents/toggleLockComponents';
import { initPostToMyLinks } from './postToMyLinks';
import { initUpdateColumnWidths } from './editColumns/updateColumnWidths';
import { initAddNewComponents } from './newComponents/addNewComponents';
import { initSortExistingComponents } from './existingComponents/sortExistingComponents';
import { initUpdateComponentsSettings } from './existingComponents/updateComponentsSettings';
import { initRemoveComponents } from './existingComponents/removeComponents';
import { initCollapseComponents } from './existingComponents/collapseComponents';
import { initUpdateNewComponentButtons } from './newComponents/updateNewComponentButtons';
import { initUpdatePlaceHolders } from './updatePlaceHolders';
import { initNewComponentsTab } from './newComponents/newComponentsTabs';
        
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
    initUpdateColumnWidths();
    initAddNewComponents();
    initSortExistingComponents();
    initUpdateComponentsSettings();
    initRemoveComponents();
    initCollapseComponents();
    initUpdateNewComponentButtons();
    initUpdatePlaceHolders();
    initNewComponentsTab();
});