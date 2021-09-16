/** 
 * Control the coponent settings panel and ensure it gets updated via ajax
 *
 */

import { gsap } from 'gsap/all';
import { slideDown, slideUp } from '@js/util/animateUtils';
import { timeout } from '@js/util/jsUtils';
import { EXISTING_COMPONENT_SELECTOR, UPDATE_URL, ON_COMPONENT_ADDED, getComponentID } from './updateHomepage';
import { updateDeletedComponent } from './deleteComponents';
import { isComponentUncollapsed, collapseComponent, uncollapseComponent, isCollapseComponentSelected } from './collapseComponents';

const ANIMATE_SPEED = 0.4;
const SETTINGS_BUTTON_SELECTOR = '[data-target="settings"]';
const SETTINGS_PANEL_SELECTOR = '[data-component-panel="settings"]';
const STATE_ATTRIBUTE = 'data-state'; //can be 'opened' or 'closed'
const QUERY_STRING_ATTRIBUTE = 'data-query-string'; //can be 'opened' or 'closed'



/**
 * initiate the setings toggle and form
 */
export const initUpdateComponentsSettings = () => {
    document.querySelectorAll(EXISTING_COMPONENT_SELECTOR).forEach((component) => init(component));
    document.addEventListener(ON_COMPONENT_ADDED, (event) => init(event.detail.component));
}

/**
 * 
 * 
 * @param { Element } 
 */
const init = (component) => {
    initToggleButton(component);
    initForm(component);
}   

/**
 * To show/hide the settings panel with the toggle button
 * 
 * @param { Element } 
 */
const initToggleButton = (component) => {
    const toggleButton = component.querySelector(SETTINGS_BUTTON_SELECTOR);
    toggleButton.addEventListener('click', (event) => {
        event.preventDefault();
        gsap.to(toggleButton, { rotation: '+=180', transformOrigin: '20px 20px', duration: ANIMATE_SPEED } );
        isOpen(component) ? close(component) : open(component);
    });
}

/**
 * Determine the state of the settings panel
 * 
 * @param { Element } 
 * 
 * @returns { Boolean }
 */
const isOpen = (component) => {
    const toggleButton = component.querySelector(SETTINGS_BUTTON_SELECTOR);
    return toggleButton.getAttribute(STATE_ATTRIBUTE) === 'opened';
}

/**
 * Open the settings panel
 * 
 * @param { Element } 
 */
const open = (component) => {
    const panel = component.querySelector(SETTINGS_PANEL_SELECTOR);
    const toggleButton = component.querySelector(SETTINGS_BUTTON_SELECTOR);
    toggleButton.setAttribute(STATE_ATTRIBUTE, 'opened');
    toggleButton.setAttribute(QUERY_STRING_ATTRIBUTE, getQueryString(component));
    slideDown(panel, ANIMATE_SPEED);
}

/**
 * To be able to compare the form query string on form submit, as don't want to submit if nothing has changed
 * 
 * @param { Element } 
 * 
 * @returns { String }  
 */
const getQueryString = (component) => {
    const panelForm = component.querySelector(SETTINGS_PANEL_SELECTOR).querySelector('form');
    const formData = Object.fromEntries(new FormData(panelForm));
    return Object.keys(formData).map(key => key + '=' + encodeURIComponent(formData[key])).join('&'); //ie cid=80&type=config&component=3393&componentname=Class%20Page%20Banners&layoutId=34&src=&src-folder-id=80&collapse=0&showComponentTitle=0'
}

/**
 * Close the settings panel
 * 
 * @param { Element } 
 */
const close = async (component) => {
    const panel = component.querySelector(SETTINGS_PANEL_SELECTOR);
    const toggleButton = component.querySelector(SETTINGS_BUTTON_SELECTOR);
    toggleButton.setAttribute(STATE_ATTRIBUTE, 'closed');
    await slideUp(panel, ANIMATE_SPEED);
}

/**
 * Ensure updates to the setting panel are updated via AJAX
 * 
 * @param { Element } 
 */
const initForm = (component) => {
    const panelForm = component.querySelector(SETTINGS_PANEL_SELECTOR).querySelector('form');
    const toggleButton = component.querySelector(SETTINGS_BUTTON_SELECTOR);
    panelForm.addEventListener('submit', (event) => {
        event.preventDefault();
        toggleButton.getAttribute(QUERY_STRING_ATTRIBUTE) === getQueryString(component) ? close(component) : saveComponentSettings(component, panelForm);
    });
}

/**
 * Save the settings to the DB
 * 
 * @param { Element } 
 * @param { HTMLFormElement } 
 */
const saveComponentSettings = async (component, form) => {
    const submitFormButton = form.querySelector('input[type="submit"]');
    try {
        submitFormButton.disabled = true;
        submitFormButton.value = 'Updating...';
        const response = await fetch(UPDATE_URL + '?' + getSaveComponentSettingsParams(form));
        const responseData = await response.text(); 
        await updateComponent(component, responseData);
    } catch (error) {
        console.warn(error);
        updateDeletedComponent(component); //currently the assumed error, that the component no longer exists
        slideUp(component.querySelector(SETTINGS_PANEL_SELECTOR), ANIMATE_SPEED);
    } finally { //now the update is complete notify with the button and close the settings, enabling once the settings are closed 
        submitFormButton.value = 'Updated';
        await close(component);
        submitFormButton.disabled = false;
        submitFormButton.value = 'Update';
    }
}

/**
 * To pass through the required FormData to delete the component in the DB
 * 
 * @param { Element } 
 * 
 * @returns { Object }  
 */
const getSaveComponentSettingsParams = (form) => {
    const formData = Object.fromEntries(new FormData(form));
    const queryString = Object.keys(formData).map(key => key + '=' + encodeURIComponent(formData[key])).join('&'); //ie cid=80&type=config&component=3393&componentname=Class%20Page%20Banners&layoutId=34&src=&src-folder-id=80&collapse=0&showComponentTitle=0'
    const params = new URLSearchParams(queryString);
    return params;
    // return { params };
}

/**
 * Now we have the returned response from the settings save, replace the old content with the new content
 * 
 * @param { Element } 
 * @param { String } 
 */
const updateComponent = async (component, responseData) => {
    
    const updatedComponent = getUpdatedComponent(component, responseData);

    const componentCollapsable = component.querySelector('[data-collapsable="true"]');
    const updatedComponentCollapsable = updatedComponent.querySelector('[data-collapsable="true"]');
    const updatedComponentCollapsableFragment = document.createRange().createContextualFragment(updatedComponentCollapsable.innerHTML); //createContextualFragment ensures any </script> tags within the element are run

    if (isComponentUncollapsed(component)) { //and is currently uncollapsed
        await collapseComponent(component); //hide the old component
    }
    
    componentCollapsable.innerHTML = ''; //empty the contents so any Vue instances can unmount
    componentCollapsable.appendChild(updatedComponentCollapsableFragment); //add in the updated collapsable 
   
    await timeout(100); //adding a delay just ensures that component scripts have had a chance to initiate so we can accurately animate the height in uncollapseComponent()

    updateComponentTitle(component, updatedComponent);

    if (!isCollapseComponentSelected(updatedComponent)) {
        await uncollapseComponent(component); //hide the old component
        window.dispatchEvent(new Event('resize')); //for components to respond to - for example swiper.js image gallerys to update to display correctly
    }

}

/**
 * To get the updated component once returned
 * 
 * @param { Element } 
 * @param { String } 
 * 
 * @returns { Element }  
 */
const getUpdatedComponent = (component, documentString) => {
    const parser = new DOMParser();
    const document = parser.parseFromString(documentString, 'text/html');
    return document.documentElement.querySelector(`#${component.id}`);
}

/**
 * Check if the title has been updated, and if so fade in to show a change
 * 
 * @param { Element } 
 * @param { Element } 
 */
const updateComponentTitle = (oldComponent, updatedComponent) => {
    const componentTitle = oldComponent.querySelector(`#component-title-${getComponentID(oldComponent)}`);
    const updatedComponentTitle = updatedComponent.querySelector(`#component-title-${getComponentID(oldComponent)}`);
    if (componentTitle.innerText !== updatedComponentTitle.innerText) {
        gsap.set(componentTitle, { opacity: 0 } );
        componentTitle.innerText = updatedComponentTitle.innerText;
        gsap.to(componentTitle, { opacity: 1, duration: ANIMATE_SPEED } );
    }
}