/**
 * A place to store Javascript properties that are needed in many places within Schoolbox.
 */

window.sbProps = {
    editors: {}, // we still need to be able to access our CKEditor instances from outside bundle scope
    disabledObserverIsAddedBySubmitButtonId: {}, // Used by wordCountSettings.ts to record whether a 'disabled' mutation observer has been added to a submit button.
    MAX_BYTES_MYSQL_MEDIUMTEXT: 16_777_215, // Maximum bytes storable in a MySQL `mediumtext` field.
    MAX_BYTES_MYSQL_TEXT: 65_535, // Maximum bytes storable in a MySQL `text` field.
};
