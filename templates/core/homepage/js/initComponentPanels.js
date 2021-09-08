/** 
 * 
 *
 */

import { gsap } from 'gsap/all';
import { slideDown, slideUp } from '@js/util/animateUtils';

/**
 * 
 * 
 * @param { DOMElement } 
 */
export const initComponentPanels = (component) => {
        
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

    const collapsePanel = component.querySelector('[data-collapsable="true"]');
    const collapseButton = component.querySelector('[data-collapser="true"]');
    collapseButton.addEventListener('click', (event) => {
        event.preventDefault();
        if (collapsePanel.getAttribute('data-collapse-state') === 'collapsed') {
            collapsePanel.setAttribute('data-collapse-state', 'uncollapsed');
            gsap.to(collapseButton, { rotation: '+=180', transformOrigin: '20px 20px', duration: 0.5 } );
            slideDown(collapsePanel, 0.5);
        } else {
            collapsePanel.setAttribute('data-collapse-state', 'collapsed');
            gsap.to(collapseButton, { rotation: '+=180', transformOrigin: '20px 20px', duration: 0.5 } );
            slideUp(collapsePanel, 0.5);
        }
    });

}

