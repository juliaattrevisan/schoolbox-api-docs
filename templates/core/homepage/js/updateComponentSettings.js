/** 
 * Control the coponent settings panel and ensure it gets updated via ajax
 *
 */

import { gsap } from 'gsap/all';
import axios from 'axios';
import { slideDown, slideUp } from '@js/util/animateUtils';
import { timeout } from '@js/util/jsUtils';
import { EXISTING_COMPONENT_SELECTOR, UPDATE_URL, getComponentID } from './updateHomepage';
import { updateDeletedComponent } from './deleteComponent';
import { isComponentUncollapsed, collapseComponent, uncollapseComponent, isCollapseComponentSelected } from './collapseComponent';

const ANIMATE_SPEED = 0.4;
const SETTINGS_BUTTON_SELECTOR = '[data-target="settings"]';
const SETTINGS_PANEL_SELECTOR = '[data-component-panel="settings"]';
const STATE_ATTRIBUTE = 'data-state'; //can be 'opened' or 'closed'
const QUERY_STRING_ATTRIBUTE = 'data-query-string'; //can be 'opened' or 'closed'



/**
 * initiate the toggle and form
 */
export const initUpdateComponentSettings = () => {
    document.querySelectorAll(EXISTING_COMPONENT_SELECTOR).forEach((component) => {   //run through every component on the page
        initToggleSettingsButton(component);
        initSettingsForm(component);
    });
}

/**
 * To show/hide the settings panel with the toggle button
 * 
 * @param { Element } 
 */
const initToggleSettingsButton = (component) => {
    const settingsButton = component.querySelector(SETTINGS_BUTTON_SELECTOR);
    settingsButton.addEventListener('click', (event) => {
        event.preventDefault();
        gsap.to(settingsButton, { rotation: '+=180', transformOrigin: '20px 20px', duration: ANIMATE_SPEED } );
        isSettingsOpen(component) ? closeSettings(component) : openSettings(component);
    });
}

/**
 * Determine the state of the settings panel
 * 
 * @param { Element } 
 * 
 * @returns { Boolean }
 */
const isSettingsOpen = (component) => {
    const settingsButton = component.querySelector(SETTINGS_BUTTON_SELECTOR);
    return settingsButton.getAttribute(STATE_ATTRIBUTE) === 'opened';
}

/**
 * Open the settings panel
 * 
 * @param { Element } 
 */
const openSettings = (component) => {
    const settingsPanel = component.querySelector(SETTINGS_PANEL_SELECTOR);
    const settingsButton = component.querySelector(SETTINGS_BUTTON_SELECTOR);
    settingsButton.setAttribute(STATE_ATTRIBUTE, 'opened');
    settingsButton.setAttribute(QUERY_STRING_ATTRIBUTE, getQueryString(component));
    slideDown(settingsPanel, ANIMATE_SPEED);
}

/**
 * To be able to compare the form query string on form submit, as don't want to submit if nothing has changed
 * 
 * @param { Element } 
 * 
 * @returns { String }  
 */
const getQueryString = (component) => {
    const settingsPanelForm = component.querySelector(SETTINGS_PANEL_SELECTOR).querySelector('form');
    const formData = Object.fromEntries(new FormData(settingsPanelForm));
    return Object.keys(formData).map(key => key + '=' + encodeURIComponent(formData[key])).join('&'); //ie cid=80&type=config&component=3393&componentname=Class%20Page%20Banners&layoutId=34&src=&src-folder-id=80&collapse=0&showComponentTitle=0'
}

/**
 * Close the settings panel
 * 
 * @param { Element } 
 */
const closeSettings = async (component) => {
    const settingsPanel = component.querySelector(SETTINGS_PANEL_SELECTOR);
    const settingsButton = component.querySelector(SETTINGS_BUTTON_SELECTOR);
    settingsButton.setAttribute(STATE_ATTRIBUTE, 'closed');
    await slideUp(settingsPanel, ANIMATE_SPEED);
}

/**
 * Ensure updates to the setting panel are updated via AJAX
 * 
 * @param { Element } 
 */
const initSettingsForm = (component) => {
    const settingsPanelForm = component.querySelector(SETTINGS_PANEL_SELECTOR).querySelector('form');
    const settingsButton = component.querySelector(SETTINGS_BUTTON_SELECTOR);
    settingsPanelForm.addEventListener('submit', (event) => {
        event.preventDefault();
        settingsButton.getAttribute(QUERY_STRING_ATTRIBUTE) === getQueryString(component) ? closeSettings(component) : saveComponentSettings(component, settingsPanelForm);
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
        const response = await axios.get(UPDATE_URL, getSaveComponentSettingsParams(form));
        await updateComponent(component, response.data);
    } catch (error) {
        console.warn(error);
        updateDeletedComponent(component); //currently the assumed error, that the component no longer exists
        slideUp(component.querySelector(SETTINGS_PANEL_SELECTOR), ANIMATE_SPEED);
    } finally { //now the update is complete notify with the button and close the settings, enabling once the settings are closed 
        submitFormButton.value = 'Updated';
        await closeSettings(component);
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
    return { params };
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