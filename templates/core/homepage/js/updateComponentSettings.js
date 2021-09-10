/** 
 * 
 *
 */

import { gsap } from 'gsap/all';
import axios from 'axios';
import { slideDown, slideUp } from '@js/util/animateUtils';

/**
 * 
 */
export const initUpdateComponentSettings = () => {
    document.querySelectorAll('[data-component]').forEach((component) => {    
        initToggleSettingsButton(component);
        initSettingsForm(component);
    });
}

/**
 * To show/hide the settings panel
 * 
 * @param { DOMElement } 
 */
const initToggleSettingsButton = (component) => {
    const settingsPanel = component.querySelector('[data-component-panel="settings"]');
    const settingsButton = component.querySelector('[data-target="settings"]');
    settingsButton.addEventListener('click', (event) => {
        event.preventDefault();
        if (settingsButton.getAttribute('data-state') === 'closed') {
            settingsButton.setAttribute('data-state', 'opened');
            gsap.to(settingsButton, { rotation: '+=180', transformOrigin: '20px 20px', duration: 0.5 } );
            slideDown(settingsPanel, 0.5);
        } else {
            settingsButton.setAttribute('data-state', 'closed');
            gsap.to(settingsButton, { rotation: '+=180', transformOrigin: '20px 20px', duration: 0.5 } );
            slideUp(settingsPanel, 0.5);
        }
    });
}

/**
 * To show/hide the settings panel
 * 
 * @param { DOMElement } 
 */
 const initSettingsForm = (component) => {
    
    const settingsPanelForm = component.querySelector('[data-component-panel="settings"]').querySelector('form');

    settingsPanelForm.addEventListener('submit', function (event) {

        event.preventDefault();
        
        saveComponentSettings(event.target);
        
        

    });

}

/**
 * Save the settings to the DB
 * 
 * @param { DOMElement } 
 */
const saveComponentSettings = async (form) => {
    try {
        await axios.get('/homepage/configForm.php', getSaveComponentSettingsParams(form));
        // await slideUp(component);
        
       
    } catch (error) {
        console.warn(error);
    } 
}

/**
 * To pass through the required FormData to delete the component in the DB
 * 
 * @param { DOMElement } 
 * 
 * @returns { Object }  
 */
const getSaveComponentSettingsParams = (form) => {
    const formData = Object.fromEntries(new FormData(form));
    const queryString = Object.keys(formData).map(key => key + '=' + encodeURIComponent(formData[key])).join('&');
    const params = new URLSearchParams(queryString);
    return { params };
}