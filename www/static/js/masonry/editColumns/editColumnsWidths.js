/**
 * Update the column width of the left/right home page component containers
 */

import { removeClassesThatContain } from "@js/util/cssUtils";
import { getMasonryConfig } from "../masonryConfig";

/**
 * Change the column width
 */
export function initEditColumnsWidths() {
    document.querySelector(getMasonryConfig().editColumns.columnWidthSelector).addEventListener("input", (event) => {
        updateColumnWidths(parseInt(event.target.value));
    });
}

/**
 * Change the column width by updating the applied class linked to width
 * @param {number} width - The width to set the column to
 */
function updateColumnWidths(width) {
    const leftContainer = document.querySelector(getMasonryConfig().editColumns.leftContainerSelector);
    removeClassesThatContain(leftContainer, "large-"); //remove any previous class that set width
    leftContainer.classList.add(`large-${width}`); //add in the new one

    const rightContainer = document.querySelector(getMasonryConfig().editColumns.rightContainerSelector);
    removeClassesThatContain(rightContainer, "large-"); //remove any previous class that set width
    rightContainer.classList.add(`large-${12 - width}`); //linked to the leftContainer, so subtract from the basis of '12' set by foundation classes
}

/**
 * Undo any changes to the width, setting back to its original value
 */
export function undoWidthChanges() {
    const range = document.querySelector(getMasonryConfig().editColumns.columnWidthSelector);
    range.value = range.getAttribute("data-original-value");
    updateColumnWidths(parseInt(range.value));
}

/**
 * Add the width setting into the form data to be saved
 * @param {FormData} formData - The form data to append the width to
 */
export function appendWidth(formData) {
    formData.append("width", document.querySelector(getMasonryConfig().editColumns.columnWidthSelector).value);
}
