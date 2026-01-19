/**
 * A place to store Javascript properties (including variables, constants, and some utility functions) that are needed
 * in many places within Schoolbox.
 *
 * Many of the properties are already set from chrome.twig prior to this script loading, there are however some parts
 * of window.sbProps that must be set here to as they use imported modules that won't be available in chrome.twig
 *
 * Types of sbProps are maintained in /types/index.d.ts and should be updated when properties are changed here.
 */

import { RosettaStone } from "@js/terminology/rosettaStone";
import { apiRequest } from "@js/util/sbx/apiUtils";
import { injectScriptsIfNotLoaded } from "@js/util/domUtils";

window.sbProps = {
    ...window.sbProps, // keep any existing properties if they exist
    apiRequest, // so any frontend code (such as in twig outside of bundles) can make use of the apiRequest utility
    editors: {}, // we still need to be able to access our CKEditor instances from outside bundle scope
    MAX_BYTES_MYSQL_MEDIUMTEXT: 16_777_215, // Maximum bytes storable in a MySQL `mediumtext` field.
    MAX_BYTES_MYSQL_TEXT: 65_535, // Maximum bytes storable in a MySQL `text` field.
    l10n: new RosettaStone([]), // A helper object for translating terminology
    injectScriptsIfNotLoaded,
};
