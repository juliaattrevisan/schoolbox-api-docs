/**
 * Edit  the colours/width of the component columns
 *
 */

import { getMasonryConfig } from "../masonryConfig";
import { initEditColumnsWidths, appendWidth } from "./editColumnsWidths";
import { initEditColumnsColours, appendColours } from "./editColumnsColours";
import { initEditColumnsButtons } from "./editColumnsButtons";
import { initResetEditColumns } from "./resetEditColumns";
import { initUndoChangesEditColumns } from "./undoChangesEditColumns";
import { initSaveEditColumns } from "./saveEditColumns";

/**
 * Fire everything up
 */
export function initEditColumns() {
    initEditColumnsWidths();
    initEditColumnsColours();
    initEditColumnsButtons();
    initResetEditColumns();
    initUndoChangesEditColumns();
    initSaveEditColumns();
}

/**
 * To pass through the required FormData to update the column settings
 *
 * @returns { FormData }
 */
export function getFormData() {
    const formData = new FormData();
    formData.append(
        getMasonryConfig().settings.formData.pageInfo.type,
        getMasonryConfig().settings.formData.pageInfo.id,
    );

    // Admin templates require a context of the selected page type to be passed through, so we need to check if it's set
    if (getMasonryConfig().settings.formData.pageInfo.context) {
        formData.append("context", getMasonryConfig().settings.formData.pageInfo.context);
    }
    appendWidth(formData);
    appendColours(formData);
    return formData;
}
