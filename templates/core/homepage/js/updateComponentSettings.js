/** 
 * 
 *
 */

import { gsap } from 'gsap/all';
import { slideDown, slideUp } from '@js/util/animateUtils';

/**
 * 
 */
export const initUpdateComponentSettings = (component) => {
    document.querySelectorAll('[data-component]').forEach((component) => {    
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
    });
}

