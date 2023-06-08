/**
 * Reset the latest changes back to the original state
 *
 */

import { UPDATE_URL } from "../updateEPortfolio";
import UpdateEPortfolioEvents from "../updateEPortfolioEvents";
import { getFormData } from "./editColumns";
import { resetColours } from "./editColumnsColours";

/**
 * Start listening for the reset event
 */
export function initResetEditColumns() {
    document.addEventListener(UpdateEPortfolioEvents.EDIT_COLUMNS_RESET, onEditColumnsReset);
}

/**
 * Save all the column settings, notifying of success / failure
 *
 * @param { Element }
 */
async function onEditColumnsReset() {
    const $toast = $('[data-relation="saveLayout"]');
    try {
        $toast.toastActivate({ text: "Saving..." });
        resetColours();
        await fetch(UPDATE_URL, {
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
            text: "Colours successfully reset.",
            css: "success",
        });
    }
}
