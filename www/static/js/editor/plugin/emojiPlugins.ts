/**
 * Returns the emoji plugins for the editor
 */

import { Emoji } from "ckeditor5";

/**
 * All the emoji plugins
 * @returns - the emoji plugins
 */
export function getEmojiPlugins(): (typeof Emoji)[] {
    return [Emoji];
}
