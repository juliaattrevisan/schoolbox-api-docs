/** 
 * Handles the majority of functionality on a Home page, including adding, sorting, and updating
 *
 */


import { initToggleLockComponents } from './toggleLockComponents';
import { initPostToMyLinks } from './postToMyLinks';
import { initUpdateColumnWidths } from './updateColumnWidths';
import { initAddNewComponents } from './addNewComponents';
import { initSortExistingComponents } from './sortExistingComponents';
import { initUpdateComponentsSettings } from './updateComponentsSettings';
import { initRemoveComponents } from './removeComponents';
import { initCollapseComponents } from './collapseComponents';
import { initUpdateComponentCounts } from './updateComponentCounts';
import { initUpdatePlaceHolders } from './updatePlaceHolders';
        
export const EXISTING_COMPONENT_SELECTOR = '[data-component]'; 
export const UPDATE_URL = '/homepage/configForm.php'; 

export const ON_COMPONENT_ADDED = 'updateHomepage.ON_COMPONENT_ADDED'; //dispatched with the { Element } component
export const ON_COMPONENT_REMOVED = 'updateHomepage.ON_COMPONENT_REMOVED'; //dispatched with the { Element } component
export const ADD_PLACE_HOLDERS = 'updateHomepage.ADD_PLACE_HOLDERS'; //to show the 'You can drag into this space' placeholders when tab opened or sorting
export const REMOVE_PLACE_HOLDERS = 'updateHomepage.REMOVE_PLACE_HOLDERS'; //to hide the 'You can drag into this space' placeholders when tab opened or sorting

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
    initUpdateComponentCounts();
    initUpdatePlaceHolders();
});