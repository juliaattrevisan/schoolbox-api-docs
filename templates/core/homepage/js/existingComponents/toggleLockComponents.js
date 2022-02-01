/** 
 * To enable/disable the editing of components
 *
 */

import { EXISTING_COMPONENT_SELECTOR } from '../updateHomepage';

/**
 * Listen to changes and enable/disable the sorting based on the status of #component-lock-switch
 */
export function initToggleLockComponents () {
    const componentLockSwitch = document.getElementById('component-lock-switch').querySelector('input');
    componentLockSwitch.addEventListener('change', (event) => {
        document.querySelectorAll(EXISTING_COMPONENT_SELECTOR).forEach((component) => updateComponentDisplay(component, event.target.checked));
    });
}

/**
 * Show or hide the relevant component editing elements 
 */
export function updateComponentDisplay (component, isUnlocked) {
    component.querySelectorAll('a[data-component-settings], [data-component-handle]').forEach((element) => {
        isUnlocked ? element.style.display = 'inline-block' : element.style.display = 'none';
    });
}

/**
 * To determine whether the toggle is locked, 
 * is also checked by new components to determine its initial display
 * 
 * @returns { Boolean }
 */
export function isToggleUnlocked () {
    return document.getElementById('component-lock-switch').querySelector('input').checked;
}