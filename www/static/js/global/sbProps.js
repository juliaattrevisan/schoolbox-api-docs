/**
 * A place to store Javascript properties (including variables, constants, and some utility functions) that are needed
 * in many places within Schoolbox.
 */

import { injectScriptsIfNotLoaded } from "@js/util/domUtils";

window.sbProps = {
    editors: {}, // we still need to be able to access our CKEditor instances from outside bundle scope
    MAX_BYTES_MYSQL_MEDIUMTEXT: 16_777_215, // Maximum bytes storable in a MySQL `mediumtext` field.
    MAX_BYTES_MYSQL_TEXT: 65_535, // Maximum bytes storable in a MySQL `text` field.
    injectScriptsIfNotLoaded,
};
