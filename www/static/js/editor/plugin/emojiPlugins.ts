/**
 * Returns the tokens plugins for the editor
 */

import {
    Emoji,
    EmojiActivity,
    EmojiFlags,
    EmojiFood,
    EmojiNature,
    EmojiObjects,
    EmojiPeople,
    EmojiPlaces,
    EmojiSymbols,
} from "@phudak/ckeditor5-emoji/src";

/**
 * All the emoji plugins
 *
 * @returns { object } - the emoji plugins
 */
export function getEmojiPlugins() {
    return [
        Emoji,
        EmojiPeople,
        EmojiNature,
        EmojiFood,
        EmojiPlaces,
        EmojiActivity,
        EmojiObjects,
        EmojiSymbols,
        EmojiFlags,
    ];
}
