/** 
 * Handles the majority of functionality on a Home page, including adding, sorting, and updating
 *
 */


import { initPostToMyLinks } from './postToMyLinks';
import { initUpdateColumnWidths } from './updateColumnWidths';
import { initAddAndSortComponents } from './addAndSortComponents';
import { initUpdateComponentsSettings } from './updateComponentsSettings';
import { initDeleteComponents } from './deleteComponents';
import { initCollapseComponents } from './collapseComponents';
        
export const EXISTING_COMPONENT_SELECTOR = '[data-component]'; 
export const UPDATE_URL = '/homepage/configForm.php'; 

export const ON_COMPONENT_ADDED = 'updateHomepage.ON_COMPONENT_ADDED'; //dispatched with the { Element } component
export const ON_COMPONENT_REMOVED = 'updateHomepage.ON_COMPONENT_REMOVED'; //dispatched with the { Element } component


/**
 * Fire everything up
 */
export const init = () => {
    initPostToMyLinks();
    initUpdateColumnWidths();
    initAddAndSortComponents();
    initUpdateComponentsSettings();
    initDeleteComponents();
    initCollapseComponents();
}

document.addEventListener('DOMContentLoaded', init);



/**
 * To retrieve the component id
 * 
 * @param { Element } 
 * 
 * @returns { String } 
 */
export const getComponentID = (component) => {
    return component.id.replace('component', ''); //strip the 'component' at the start to just get the id number
}