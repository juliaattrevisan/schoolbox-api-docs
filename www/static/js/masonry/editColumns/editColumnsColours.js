/**
 * Update the colours of the home page components
 * There are currently four colour pickers.
 * The Text and Background colour of the Heading,
 * as well as the Text and Background colour of the Content
 * When changing these colour pickers, all the components within the
 * currently selected Columns will change
 *
 */

import { getMasonryConfig } from "../masonryConfig";

/**
 * Change the column colours
 */
export function initEditColumnsColours() {
    initCheckboxes();
    initColourPickers();
    displayDefaultColourPickerColours();
}

/**
 * Prevent all column checkboxes from being de-selected,
 * so at least one checkbox has to be selected at all times
 * This is so we are always changing the colour of at least one column
 */
function initCheckboxes() {
    getAllColumnCheckboxes().forEach((checkbox) => {
        checkbox.addEventListener("change", () => {
            if (getSelectedColumnCheckboxes().length === 0) {
                //if none are currently selected after the change
                checkbox.checked = true; //put the just de-selected checkbox back to selected
            }
            displaySelectedColourPickerColours();
        });
    });
}

/**
 * Get all the column checkboxes to be able to loop through them
 *
 * @returns { Array }
 */
function getAllColumnCheckboxes() {
    return Array.from(document.querySelectorAll("[data-column-selector]"));
}

/**
 * Check which of the checkboxes are currently selected,
 * so we know which columns to apply colour changes to
 *
 * @returns { Array }
 */
function getSelectedColumnCheckboxes() {
    return getAllColumnCheckboxes().filter((checkbox) => checkbox.checked);
}

/**
 * Initiate the colour pickers
 */
function initColourPickers() {
    const currentPickerId = 0;
    const colourPickers = getAllColourPickers();
    colourPickers.forEach((colourPicker) => {
        // Ensure each picker has a unique ID to use as a target
        if (colourPicker.id === "") {
            colourPicker.id = `colourPicker${currentPickerId}`;
            currentPickerId += 1;
        }

        document.dispatchEvent(
            new CustomEvent("colourPickerInitialise", {
                detail: {
                    el: `#${colourPicker.id}`,
                    clearButton: true,
                },
            }),
        );

        colourPicker.addEventListener("input", (event) => {
            onColourChange(event.currentTarget, event.currentTarget.value);
        });
        colourPicker.addEventListener("change", (event) => {
            onColourChange(event.currentTarget, event.currentTarget.value);
        });
    });
}

/**
 * Update the currently selected columns with the new colour and
 * ensure the new value is saved within the getMasonryConfig().editColumns.colours object
 *
 * @param { Element }
 * @param { String }
 */
function onColourChange(element, colour) {
    // If the colour has been set to empty, don't update (the user hasn't triggered this change, we have)
    if (colour === "") {
        return;
    }

    const elementId = element.id;
    const selectedColumnCheckboxes = getSelectedColumnCheckboxes();
    for (let i = 0; i < selectedColumnCheckboxes.length; i++) {
        //run though each selected column and apply the colour change
        const colourId = `${selectedColumnCheckboxes[i].id}_${elementId}`; //for example 'top_head_font'
        updateColour(colourId, colour);
    }
}

/**
 * Show the new colour change applied to the selected columns
 * Updating the pre-existing CSS property will automatically change the colour
 *
 * @param { Element }
 * @param { String }
 */
function updateColour(colourId, colour) {
    getMasonryConfig().editColumns.colours.pickers[colourId] = colour; //update the object for possible future save / reset / undo
    const colourProperty = `--${colourId.replace(/_/g, "-")}`;
    document.querySelector(getMasonryConfig().settings.layoutSelector).style.setProperty(colourProperty, colour);
}

/**
 * Display the initial colour picker colours,
 * if there's different colour values for the top & left columns for example,
 * no colour will be set. So the colour will only be set if all
 * the columns have the same colour.
 */
function displayDefaultColourPickerColours() {
    updateColourPickerColours(getAllColumnCheckboxes());
}

/**
 * Display the current colour picker colours based on which columns are currently selected,
 * if there's different colour values for the top & left columns for example,
 * no colour will be set. So the colour will only be set if all
 * the selected columns have the same colour.
 */
function displaySelectedColourPickerColours() {
    updateColourPickerColours(getSelectedColumnCheckboxes());
}

/**
 * Display the current colour picker colours based on which column checkboxes are passed through,
 * if there's different colour values for the top & left columns for example,
 * no colour will be set. So the colour will only be set if all
 * columns checkboxes have the same colour.
 *
 * @param { array }
 */
function updateColourPickerColours(columnCheckboxes) {
    const colourPickers = getAllColourPickers();
    colourPickers.forEach((colourPicker) => {
        const checkboxes = columnCheckboxes; //use to retrieve the relevant colours
        const colourList = checkboxes.map(
            (checkbox) => getMasonryConfig().editColumns.colours.pickers[`${checkbox.id}_${colourPicker.id}`],
        ); //for example top_head_font, left_head_font and right_head_font
        const areAllColoursTheSame = colourList.every((value, index, array) => value === array[0]); //are all colour values within the array the same as the first value in the array

        const initialColour = areAllColoursTheSame ? colourList[0] : null; //if all the colours aren't the same, set to null

        colourPicker.value = initialColour;
        colourPicker.dispatchEvent(new Event("input", { bubbles: true }));
    });
}

/**
 * To loop through when setting initial colours or resetting / undoing changes
 *
 * @returns { Array }
 */
function getAllColourPickers() {
    return Array.from(document.querySelectorAll(getMasonryConfig().editColumns.colourPickersSelector));
}

/**
 * Add the colour properties into the form data to be saved
 *
 * @param { FormData }
 */
export function appendColours(formData) {
    for (const [key, value] of Object.entries(getMasonryConfig().editColumns.colours.pickers)) {
        if (value === "") {
            // If the value is none, then fall back to the original colour
            // This might happen if the user has different colours for each column
            value = COLOURS.original[key];
        }
        //loop through the key/value pairs for each value and append to FormData
        formData.append(`colors[${key}]`, value);
    }
}

/**
 * Reset all the colours back to the default selections.
 * This represents the admin set colours used as the default
 */
export function resetColours() {
    updateAllColours(getMasonryConfig().editColumns.colours.defaults);
}

/**
 * Undo all the colour changes back to the original selections.
 * This represents the colours at page load, prior to any further Saves
 */
export function undoColourChanges() {
    updateAllColours(getMasonryConfig().editColumns.colours.original);
}

/**
 * Reset all the colours back to the default or original selections
 *
 * @param { Object }
 */
function updateAllColours(colourList) {
    for (let [key, value] of Object.entries(colourList)) {
        //loop through the key/value pairs to revert all colours back

        // If value is of format "ffffff" then append "#" to correct HEX format, otherwise leave as is
        value = value.length === 6 ? `#${value}` : value;

        updateColour(key, value); //in this case key is 'top_head_font' and value could be 'rgba()'
    }
    displayDefaultColourPickerColours(); //update the colour pickers to reflect the reset
}
