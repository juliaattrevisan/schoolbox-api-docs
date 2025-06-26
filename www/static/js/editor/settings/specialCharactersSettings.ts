import type { SpecialCharactersConfig } from "ckeditor5";

/**
 * Returns the settings for the SpecialCharacters button
 * @returns - the SpecialCharacters settings
 */
export function getSpecialCharactersSettings(): { specialCharacters: SpecialCharactersConfig } {
    return {
        specialCharacters: {
            order: ["Arrows", "Currency", "Latin", "Language", "Mathematical", "Text"],
        },
    };
}
