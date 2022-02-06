/** 
 * Update the column width of the left/right home page component containers
 *
 */

import { removeClassesThatContain } from '@js/util/cssUtils';

/**
 * Change the column width
 * 
 * @todo look to remove the jQuery foundation reference 
 */
export function initEditColumnWidths () {
    $('[data-interaction="column-width"]').on('change.fndtn.slider', function(event) {
        updateColumnWidths(parseInt(event.target.getAttribute('data-slider')));
    });
}

/**
 * Change the column width by updating the applied class linked to width
 * 
 * @param { Number } 
 */
function updateColumnWidths (width) {

    const leftContainer = document.querySelector('[data-position="left"]');
    removeClassesThatContain(leftContainer, 'large-'); //remove any previous class that set width
    leftContainer.classList.add(`large-${width}`); //add in the new one

    const rightContainer = document.querySelector('[data-position="right"]');
    removeClassesThatContain(rightContainer, 'large-'); //remove any previous class that set width
    rightContainer.classList.add(`large-${12-width}`); //linked to the leftContainer, so subract from the basis of '12' set by foundation classes

}

/**
 * Add the width setting into the form data to be saved
 * 
 * @param { FormData } 
 */
export function appendWidth (formData) {
    formData.append('width', document.querySelector('[data-interaction="column-width"]').getAttribute('data-slider'));
}