/** 
 * To enable/disable the editing of components
 *
 */

/**
 * Listen to changes and enable/disable the sorting based on the status of #component-lock-switch
 */
export const initToggleLockComponents = () => {
    const componentLockSwitch = document.getElementById('component-lock-switch').querySelector('input');
    componentLockSwitch.addEventListener('change', () => updateDisplay());
}

/**
 * Show or hide the relevant component editing elements 
 */
const updateDisplay = () => {
    document.querySelectorAll('a[data-component-settings], [data-component-handle]').forEach((element) => {
        isToggleLocked ? element.style.display = 'inline-block' : element.style.display = 'none';
    });
}

/**
 * To determine whether the toggle is locked, 
 * is also checked by new components to determine its initial display
 * 
 * @returns { Boolean }
 */
export const isToggleLocked = () => {
    return document.getElementById('component-lock-switch').querySelector('input').checked;
}