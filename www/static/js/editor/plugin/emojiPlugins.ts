/**
 * Returns the emoji plugins for the editor
 */

import { Emoji, Mention } from "ckeditor5";

/**
 * All the emoji plugins
 * @returns - the emoji plugins
 */
export function getEmojiPlugins(): (typeof Emoji | typeof Mention)[] {
    return [Emoji, Mention];
}
