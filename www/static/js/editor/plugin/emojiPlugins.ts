/**
 * Returns the tokens plugins for the editor
 */

import type { PluginConstructor } from "@ckeditor/ckeditor5-core";
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
 * @returns - the emoji plugins
 */
export function getEmojiPlugins(): PluginConstructor[] {
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
