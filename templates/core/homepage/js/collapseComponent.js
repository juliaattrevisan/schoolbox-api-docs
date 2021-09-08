/** 
 * 
 *
 */

import { gsap } from 'gsap/all';
import { slideDown, slideUp } from '@js/util/animateUtils';

/**
 * 
 */
 export const initCollapseComponent = (component) => {
    document.querySelectorAll('[data-component]').forEach((component) => {
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
    });
}

 