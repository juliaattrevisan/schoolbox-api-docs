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
export function initEditColumnsWidths () {
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
 * Undo any changes to the width, setting back to its original state
 * @todo - Note, there was previously an issue where set_value was buggy: https://github.com/alaress/schoolbox/pull/7532
 * This doesn't appear to be the case anymore however
 * 
 * @param { FormData } 
 */
export function undoWidthChanges () {
    const $slider = $('[data-interaction="column-width"]');
    $slider.foundation(
        'slider',
        'set_value',
        $slider.data('original-value')
    );
    updateColumnWidths($slider.data('original-value'));
}

/**
 * Add the width setting into the form data to be saved
 * 
 * @param { FormData } 
 */
export function appendWidth (formData) {
    formData.append('width', document.querySelector('[data-interaction="column-width"]').getAttribute('data-slider'));
}