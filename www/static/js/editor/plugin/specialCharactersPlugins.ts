/**
 * Returns the special character plugins for the editor
 */

import { SpecialCharacters, SpecialCharactersEssentials } from "ckeditor5";

/**
 * All the SpecialCharacters plugins
 * @returns - the SpecialCharacters plugins
 */
export function getSpecialCharactersPlugins(): (typeof SpecialCharacters | typeof SpecialCharactersEssentials)[] {
    return [SpecialCharacters, SpecialCharactersEssentials];
}
