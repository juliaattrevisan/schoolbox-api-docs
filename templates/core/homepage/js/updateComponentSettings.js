/** 
 * Control the coponent settings panel and ensure it gets updated via ajax
 *
 */

import { gsap } from 'gsap/all';
import axios from 'axios';
import { slideDown, slideUp } from '@js/util/animateUtils';
import { EXISTING_COMPONENT_SELECTOR, UPDATE_URL, getComponentID } from './updateHomepage';
import { updateDeletedComponent } from './deleteComponent';
import { isComponentUncollapsed, collapseComponent, uncollapseComponent, isCollapseComponentSelected } from './collapseComponent';

const SETTINGS_BUTTON_SELECTOR = '[data-target="settings"]';
const SETTINGS_PANEL_SELECTOR = '[data-component-panel="settings"]';



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
        gsap.to(settingsButton, { rotation: '+=180', transformOrigin: '20px 20px', duration: 0.5 } );
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
    return settingsButton.getAttribute('data-state') === 'opened';
}

/**
 * Open the settings panel
 * 
 * @param { Element } 
 */
const openSettings = (component) => {
    const settingsPanel = component.querySelector(SETTINGS_PANEL_SELECTOR);
    const settingsButton = component.querySelector(SETTINGS_BUTTON_SELECTOR);
    settingsButton.setAttribute('data-state', 'opened');
    slideDown(settingsPanel, 0.5);
}

/**
 * Close the settings panel
 * 
 * @param { Element } 
 */
 const closeSettings = (component) => {
    const settingsPanel = component.querySelector(SETTINGS_PANEL_SELECTOR);
    const settingsButton = component.querySelector(SETTINGS_BUTTON_SELECTOR);
    settingsButton.setAttribute('data-state', 'closed');
    slideUp(settingsPanel, 0.5);
}

/**
 * Ensure updates to the setting panel are updated via AJAX
 * 
 * @param { Element } 
 */
 const initSettingsForm = (component) => {
    const settingsPanelForm = component.querySelector(SETTINGS_PANEL_SELECTOR).querySelector('form');
    settingsPanelForm.addEventListener('submit', (event) => {
        event.preventDefault();
        saveComponentSettings(component, settingsPanelForm);
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
        const response = await axios.get(UPDATE_URL, getSaveComponentSettingsParams(form));
        closeSettings(component)
        updateComponent(component, response.data);
    } catch (error) {
        console.warn(error);
        updateDeletedComponent(component); //currently the assumed error, that the component no longer exists
        slideUp(component.querySelector(SETTINGS_PANEL_SELECTOR), 0.5);
    } finally {
        submitFormButton.disabled = false;
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
    const updatedComponentPanelFragment = document.createRange().createContextualFragment(updatedComponent.innerHTML);
    document.body.appendChild(updatedComponentPanelFragment);
    
    updateComponentTitle(component, updatedComponent);

    const oldCollapsePanel = component.querySelector('[data-collapsable="true"]');
    // const newCollapsePanel = updatedComponent.querySelector('[data-collapsable="true"]');
    // const newCollapsePanel = document.createRange().createContextualFragment(updatedComponent.querySelector('[data-collapsable="true"]').outerHTML);

    if (isComponentUncollapsed(component)) { //and is currently uncollapsed
        await collapseComponent(component); //hide the old component
    }
    
    // oldCollapsePanel.innerHTML = '';
    oldCollapsePanel.parentNode.replaceChild(updatedComponentPanelFragment, oldCollapsePanel); //replace with the new component

    // newCollapsePanel.style.display = 'none';

    // await waitFor(200);

    // oldCollapsePanel.parentNode.replaceChild(document.createRange().createContextualFragment(newCollapsePanel.outerHTML), oldCollapsePanel); //replace with the new component
    // setInnerHTML(oldCollapsePanel, newCollapsePanel.innerHTML);

    if (!isCollapseComponentSelected(updatedComponent)) {
        await uncollapseComponent(component); //hide the old component
        window.dispatchEvent(new Event('resize')); //for components to respond to - for example swiper.js image gallerys to update to display correctly
    }

}

const waitFor = delay => new Promise(resolve => setTimeout(resolve, delay));


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
    // const updatedComponent = document.documentElement.querySelector(`#${component.id}`);
    // return document.createRange().createContextualFragment(updatedComponent.innerHTML); //createContextualFragment ensures any </script> tags within the component are run
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
        gsap.to(componentTitle, { opacity: 1, duration: 0.5 } );
    }
}