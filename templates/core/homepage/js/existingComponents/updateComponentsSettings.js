/** 
 * Control the component settings panel and ensure it gets updated via ajax
 *
 */

import { gsap } from 'gsap/all';
import { slideDown, slideUp } from '@js/util/animateUtils';
import { getLoadedHTML } from '@js/util/domUtils';
import { EXISTING_COMPONENT_SELECTOR, UPDATE_URL } from '../updateHomepage';
import UpdateHomepageEvents from '../updateHomepageEvents';
import { updateRemovedComponent } from './removeComponents';

const ANIMATE_SPEED = 0.3;
const BUTTON_SELECTOR = '[data-target="settings"]';
const PANEL_SELECTOR = '[data-component-panel="settings"]';
const STATE_ATTRIBUTE = 'data-state'; //can be 'opened' or 'closed'
const QUERY_STRING_ATTRIBUTE = 'data-query-string'; //to store the current settings to be sent to the DB on update



/**
 * Initiate the setings toggle and form for each component and when a 
 * new component is added or udpated
 */
export function initUpdateComponentsSettings () {
    document.querySelectorAll(EXISTING_COMPONENT_SELECTOR).forEach((component) => init(component));
    document.addEventListener(UpdateHomepageEvents.COMPONENT_ADDED, (event) => init(event.detail.component));
    document.addEventListener(UpdateHomepageEvents.COMPONENT_UPDATED, (event) => init(event.detail.component));
}

/**
 * Set up the settings buttons and what happens on form submission
 * 
 * @param { Element } 
 */
function init (component) {
    initToggleButtons(component);
    initForm(component);
}   

/**
 * To show/hide the settings panel with the toggle button.
 * There's also sometimes a settings toggle button in the empty component state as well,
 * so check for that
 * 
 * @param { Element } 
 */
function initToggleButtons (component) {
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    toggleButton.addEventListener('click', (event) => {
        event.preventDefault();
        const buttonRotation = isOpen(component) ? 0 : 90; 
        gsap.to(toggleButton, { rotation: buttonRotation, transformOrigin: '20px 20px', duration: ANIMATE_SPEED } );
        isOpen(component) ? close(component) : open(component);
    });
    const emptyStateToggleButton = component.querySelector(`${BUTTON_SELECTOR}.empty-edit`);
    if (emptyStateToggleButton) {
        emptyStateToggleButton.addEventListener('click', (event) => {
            event.preventDefault();
            isOpen(component) ? close(component) : open(component);
        });
    }
}

/**
 * Determine the state of the settings panel
 * 
 * @param { Element } 
 * 
 * @returns { Boolean }
 */
export function isOpen (component) {
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    return toggleButton.getAttribute(STATE_ATTRIBUTE) === 'opened';
}

/**
 * Open the settings panel
 * 
 * @param { Element } 
 */
function open (component) {
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    toggleButton.setAttribute(STATE_ATTRIBUTE, 'opened');
    toggleButton.setAttribute(QUERY_STRING_ATTRIBUTE, getQueryString(component));
    const panel = component.querySelector(PANEL_SELECTOR);
    slideDown(panel, ANIMATE_SPEED);
}

/**
 * To be able to compare the form query string on form submit, as we don't want to submit if nothing has changed
 * 
 * @param { Element } 
 * 
 * @returns { String }  
 */
function getQueryString (component) {
    const panelForm = component.querySelector(PANEL_SELECTOR).querySelector('form');
    const formData = Object.fromEntries(new FormData(panelForm));
    return Object.keys(formData).map(key => key + '=' + encodeURIComponent(formData[key])).join('&'); //ie cid=80&type=config&component=3393&componentname=Class%20Page%20Banners&layoutId=34&src=&src-folder-id=80&collapse=0&showComponentTitle=0'
}

/**
 * Close the settings panel
 * 
 * @param { Element } 
 */
async function close (component) {
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
    toggleButton.setAttribute(STATE_ATTRIBUTE, 'closed');
    const panel = component.querySelector(PANEL_SELECTOR);
    await slideUp(panel, ANIMATE_SPEED);
}

/**
 * Ensure updates to the settings panel are updated via AJAX
 * 
 * @param { Element } 
 */
function initForm (component) {
    const panelForm = component.querySelector(PANEL_SELECTOR).querySelector('form');
    const toggleButton = component.querySelector(BUTTON_SELECTOR);
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
async function saveComponentSettings (component, form) {
    const submitFormButton = form.querySelector('input[type="submit"]');
    try {
        document.dispatchEvent(new Event(UpdateHomepageEvents.PROCESSING_START));
        submitFormButton.disabled = true;
        submitFormButton.value = 'Updating...';
        const response = await fetch(UPDATE_URL + '?' + getSaveComponentSettingsParams(form));
        const responseData = await response.text(); 
        await updateComponent(component, responseData);
    } catch (error) {
        console.warn(error);
        updateRemovedComponent(component); //currently the assumed error, that the component no longer exists
        slideUp(component.querySelector(PANEL_SELECTOR), ANIMATE_SPEED);
    } finally {
        document.dispatchEvent(new Event(UpdateHomepageEvents.PROCESSING_END));
    }
}

/**
 * To pass through the required FormData to update the component in the DB
 * 
 * @param { Element } 
 * 
 * @returns { Object }  
 */
function getSaveComponentSettingsParams (form) {
    const formData = Object.fromEntries(new FormData(form));
    const queryString = Object.keys(formData).map(key => key + '=' + encodeURIComponent(formData[key])).join('&'); //ie cid=80&type=config&component=3393&componentname=Class%20Page%20Banners&layoutId=34&src=&src-folder-id=80&collapse=0&showComponentTitle=0'
    const params = new URLSearchParams(queryString);
    return params;
}

/**
 * Now we have the returned response from the settings save, replace the old component with the new component
 * 
 * @param { Element } 
 * @param { String } 
 */
async function updateComponent (component, responseData) {
    const updatedComponent = getUpdatedComponent(component, responseData);
    const newComponent = await getLoadedHTML(updatedComponent.outerHTML); //enure the new html is properly loaded
    component.replaceWith(newComponent); //switch over to the newly updated component
    gsap.to(newComponent, { //fade in the updated component
        duration: ANIMATE_SPEED,
        opacity: 1, 
        startAt: { opacity: 0 },
    } );
    document.dispatchEvent(new CustomEvent(UpdateHomepageEvents.COMPONENT_UPDATED, { detail: { component: newComponent } } ) );
}

/**
 * To get the updated component as an Element once returned as it's 
 * currently in the form of a String
 * 
 * @param { Element } 
 * @param { String } 
 * 
 * @returns { Element }  
 */
function getUpdatedComponent (component, documentString) {
    const parser = new DOMParser();
    const document = parser.parseFromString(documentString, 'text/html');
    return document.documentElement.querySelector(`#${component.id}`);
}