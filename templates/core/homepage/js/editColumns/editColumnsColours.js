/** 
 * Update the colours of the home page components
 * There are currently four colour pickers.
 * The Text and Background colour of the Heading, 
 * as well as the Text and Background colour of the Content
 * When changing these colour pickers, all the components within the 
 * currently selected Columns will change 
 * 
 */

const COLOUR_PICKERS_SELECTOR = '[data-colorpicker]';
const COLOURS = colours; //is a global variable from templates/core/homepage/index.html


/**
 * Change the column colours
 * 
 */
export function initEditColumnsColours () {
    initCheckboxes();
    initColourPickers();
    displayDefaultColourPickerColours();
}

/**
 * Prevent all column checkboxes from being de-selected, 
 * so at least one checkbox has to be selected at all times
 * This is so we are always changing the colour of at least one column
 */
function initCheckboxes () {
   getAllColumnCheckboxes().forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            if (getSelectedColumnCheckboxes().length === 0) { //if none are currently selected after the change
                checkbox.checked = true; //put the just de-selected checkbox back to selected
            } 
        });
    });
}

/**
 * Get all the column checkboxes to be able to loop through them
 * 
 * @returns { Array }
 */
 function getAllColumnCheckboxes () {
    return Array.from(document.querySelectorAll('[data-column-selector]'));
}

/**
 * Check which of the checkboxes are currently selected,
 * so we know which columns to apply colour changes to
 * 
 * @returns { Array }
 */
function getSelectedColumnCheckboxes () {
    return getAllColumnCheckboxes().filter((checkbox) => checkbox.checked);
}

/**
 * Initiate the colour pickers
 */
function initColourPickers () {
    $(COLOUR_PICKERS_SELECTOR).spectrum({
        showPalette: false,
        showSelectionPalette: false,
        showButtons: false,
        showAlpha: true,
        showInitial: true,
        showInput: true,
        clickoutFiresChange: true,
        preferredFormat: 'hex',
        replacerClassName: 'colorBox',
        allowEmpty: true,
    });
    $(COLOUR_PICKERS_SELECTOR).on('change.spectrum, move.spectrum', (event, colour) => { 
        //apply when there's a colour selected, otherwise set the colour as black transparent,
        //as the 'Clear Color Selection' button has been seleced
        const colourAsRGB = colour instanceof tinycolor ? colour.toRgbString() : 'rgba(0, 0, 0, 0)';
        onColourChange(event.currentTarget, colourAsRGB);
    });
}

/**
 * Update the currently selected columns with the new colour and 
 * ensure the new value is saved within the COLOURS object
 * 
 * @param { Element }
 * @param { String }
 */
function onColourChange (element, colourAsRGB) {
    const elementId = element.id;
    const selectedColumnCheckboxes = getSelectedColumnCheckboxes();
    for (let i = 0; i < selectedColumnCheckboxes.length; i++) { //run though each selected column and apply the colour change
        const colourId = `${selectedColumnCheckboxes[i].id}_${elementId}`; //for example 'top_head_font'
        updateColour(colourId, colourAsRGB);
    }
}

/**
 * Show the new colour change applied to the selected columns
 * Updating the pre-existing CSS property will automatically change the colour
 * 
 * @param { Element }
 * @param { String }
 */
function updateColour (colourId, colourAsRGB) {
    COLOURS.pickers[colourId] = colourAsRGB; //update the object for possible future save / reset / undo
    const colourProperty = `--${colourId.replace(/_/g, '-')}`;
    const homepage = document.getElementById('component-layout');
    homepage.style.setProperty(colourProperty, colourAsRGB)
}

/**
 * Display the initial colour picker colours,
 * if there's different colour values for the top & left columns for example,
 * no colour will be set. So the colour will only be set if all
 * the columns have the same colour.
 */
function displayDefaultColourPickerColours () {
    const colourPickers = getAllColourPickers();
    colourPickers.forEach((colourPicker) => {
        const allCheckboxes = getAllColumnCheckboxes(); //use to retrieve the relevant colours
        const colourList = allCheckboxes.map((checkbox) => COLOURS.pickers[`${checkbox.id}_${colourPicker.id}`]); //for example top_head_font, left_head_font and right_head_font
        const areAllColoursTheSame = colourList.every((value, index, array) => value === array[0]); //are all colour values within the array the same as the first value in the array
        const initialColour = areAllColoursTheSame ? colourList[0] : null; //if all the colours aren't the same, set to null
        $(colourPicker).spectrum('set', initialColour);
    });
}

/**
 * To loop through when setting initial colours or resetting / undoing changes
 * 
 * @returns { Array }
 */
function getAllColourPickers () {
    return Array.from(document.querySelectorAll(COLOUR_PICKERS_SELECTOR));
}

/**
 * Add the colour properties into the form data to be saved
 * 
 * @param { FormData } 
 */
export function appendColours (formData) {
    for (const [key, value] of Object.entries(COLOURS.pickers)) { //loop through the key/value pairs for each value and append to FormData
        formData.append(`colors[${key}]`, value);
    }
}

/**
 * Reset all the colours back to the default selections.
 * This represents the admin set colours used as the default
 */
export function resetColours () {
    updateAllColours(COLOURS.defaults);
}

/**
 * Undo all the colour changes back to the original selections.
 * This represents the colours at page load, prior to any further Saves
 */
export function undoColourChanges () {
    updateAllColours(COLOURS.original);
}

/**
 * Reset all the colours back to the default or original selections
 * 
 * @param { Object }
 */
function updateAllColours (colourList) {
    for (const [key, value] of Object.entries(colourList)) { //loop through the key/value pairs to revert all colours back
        //need to ensure assigned colour is always rgb, as if we are moving from rgba to hex, the alpha will not convert
        const colourAsRGB = tinycolor(value).toRgbString(); 
        updateColour(key, colourAsRGB); //in this case key is 'top_head_font' and value could be 'rgba()'
    }
    displayDefaultColourPickerColours(); //update the colour pickers to reflect the reset
}
