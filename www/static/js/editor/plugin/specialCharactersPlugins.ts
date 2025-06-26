/**
 * Returns the special character plugins for the editor
 */

import { SpecialCharacters, SpecialCharactersEssentials, type Editor } from "ckeditor5";

/**
 * Adds a collection of language-specific special characters to the SpecialCharacters plugin.
 * Includes characters for Spanish, German, French, and Italian languages.
 * @param editor - the editor instance to add the special characters to
 */
function SpecialCharactersLanguages(editor: Editor): void {
    editor.plugins.get("SpecialCharacters").addItems(
        "Language",
        [
            { title: "a with acute", character: "á" },
            { title: "a with grave", character: "à" },
            { title: "a with circumflex", character: "â" },
            { title: "a with umlaut", character: "ä" },
            { title: "A with acute", character: "Á" },
            { title: "A with grave", character: "À" },
            { title: "A with circumflex", character: "Â" },
            { title: "A with umlaut", character: "Ä" },
            { title: "ae ligature", character: "æ" },
            { title: "AE ligature", character: "Æ" },
            { title: "c with cedilla", character: "ç" },
            { title: "C with cedilla", character: "Ç" },
            { title: "e with acute", character: "é" },
            { title: "e with grave", character: "è" },
            { title: "e with circumflex", character: "ê" },
            { title: "e with diaeresis", character: "ë" },
            { title: "E with acute", character: "É" },
            { title: "E with grave", character: "È" },
            { title: "E with circumflex", character: "Ê" },
            { title: "E with diaeresis", character: "Ë" },
            { title: "i with acute", character: "í" },
            { title: "i with grave", character: "ì" },
            { title: "i with circumflex", character: "î" },
            { title: "i with diaeresis", character: "ï" },
            { title: "I with acute", character: "Í" },
            { title: "I with grave", character: "Ì" },
            { title: "I with circumflex", character: "Î" },
            { title: "I with diaeresis", character: "Ï" },
            { title: "inverted exclamation mark", character: "¡" },
            { title: "inverted question mark", character: "¿" },
            { title: "n with tilde", character: "ñ" },
            { title: "N with tilde", character: "Ñ" },
            { title: "o with acute", character: "ó" },
            { title: "o with grave", character: "ò" },
            { title: "o with circumflex", character: "ô" },
            { title: "o with umlaut", character: "ö" },
            { title: "O with acute", character: "Ó" },
            { title: "O with grave", character: "Ò" },
            { title: "O with circumflex", character: "Ô" },
            { title: "O with umlaut", character: "Ö" },
            { title: "oe ligature", character: "œ" },
            { title: "OE ligature", character: "Œ" },
            { title: "sharp s", character: "ß" },
            { title: "u with acute", character: "ú" },
            { title: "u with grave", character: "ù" },
            { title: "u with circumflex", character: "û" },
            { title: "u with diaeresis", character: "ü" },
            { title: "U with acute", character: "Ú" },
            { title: "U with grave", character: "Ù" },
            { title: "U with circumflex", character: "Û" },
            { title: "U with diaeresis", character: "Ü" },
            { title: "y with diaeresis", character: "ÿ" },
            { title: "Y with diaeresis", character: "Ÿ" },
        ],
        { label: "Language" },
    );
}

/**
 * All the SpecialCharacters plugins
 * @returns - the SpecialCharacters plugins
 */
export function getSpecialCharactersPlugins(): (
    | typeof SpecialCharacters
    | typeof SpecialCharactersEssentials
    | typeof SpecialCharactersLanguages
)[] {
    return [SpecialCharacters, SpecialCharactersEssentials, SpecialCharactersLanguages];
}
