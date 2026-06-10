/**
 * Save the latest column width/colour changes
 *
 */

import { getMasonryConfig } from "../masonryConfig";
import UpdateMasonryEvents from "../updateMasonryEvents";
import { getFormData } from "./editColumns";
import { validateHeadingColourContrast } from "./editColumnsColours";

/**
 * Start listening for the save event
 */
export function initSaveEditColumns() {
    document.addEventListener(UpdateMasonryEvents.EDIT_COLUMNS_SAVE, onEditColumnsSave);
}

/**
 * Save all the column settings, notifying of success / failure
 *
 * @param { Element }
 */
async function onEditColumnsSave() {
    if (!validateHeadingColourContrast()) {
        return;
    }

    const $toast = $('[data-relation="saveLayout"]');
    try {
        $toast.toastActivate({ text: "Saving..." });
        await fetch(getMasonryConfig().settings.updateUrl, {
            method: "POST",
            body: getFormData(),
        });
    } catch (error) {
        console.warn(error);
        $toast.toastTimeout({
            text: "Unable to save column settings.",
            css: "alert",
        });
    } finally {
        $toast.toastTimeout({
            text: "Columns successfully saved.",
            css: "success",
        });
    }
}
