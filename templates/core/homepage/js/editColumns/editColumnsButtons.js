/** 
 * To dispatch the relevant events for each
 *
 */


import UpdateHomepageEvents from '../updateHomepageEvents';


/**
 * Start listening for button events
 */
export function initEditColumnButtons () {
    document.querySelector('[data-role="reset-to-default"]').addEventListener('click', onResetSelected);
    document.querySelector('[data-role="undo"]').addEventListener('click', onUndoChangesSelected);
    document.querySelector('[data-role="save"]').addEventListener('click', onSaveSelected);
}

/**
 * Reset all colours/widths back to the original defaults, 
 * not considering any changes made by the user
 * 
 * @param { Event }
 */
function onResetSelected (event) {
    event.preventDefault();
    document.dispatchEvent(new Event(UpdateHomepageEvents.EDIT_COLUMNS_RESET));
}

/**
 * Undo the most recent changes, even if they have been saved
 * 
 * @param { Event }
 */
function onUndoChangesSelected (event) {
    event.preventDefault();
    document.dispatchEvent(new Event(UpdateHomepageEvents.EDIT_COLUMNS_UNDO_CHANGES));
}

/**
 * Update the config with the latest colunn width and colour settings
 * 
 * @param { Event }
 */
function onSaveSelected (event) {
    event.preventDefault();
    document.dispatchEvent(new Event(UpdateHomepageEvents.EDIT_COLUMNS_SAVE));
}

