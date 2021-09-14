/** 
 * Handles the majority of functionality on a Home page, including adding, sorting, and updating
 *
 */


import { initAddAndSortComponents } from './addAndSortComponents';
import { initUpdateComponentSettings } from './updateComponentSettings';
import { initDeleteComponent } from './deleteComponent';
import { initCollapseComponent } from './collapseComponent';
        
export const EXISTING_COMPONENT_SELECTOR = '[data-component]'; 
export const UPDATE_URL = '/homepage/configForm.php'; 

export const ON_COMPONENT_ADDED = 'ON_COMPONENT_ADDED'; 
export const ON_COMPONENT_REMOVED = 'ON_COMPONENT_REMOVED'; 


/**
 * Fire everything up
 */
export const init = () => {
    initAddAndSortComponents();
    initUpdateComponentSettings();
    initDeleteComponent();
    initCollapseComponent();
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