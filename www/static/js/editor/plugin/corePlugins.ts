/**
 * Returns the plugins that all editor type instances use
 */

import Essentials from "@ckeditor/ckeditor5-essentials/src/essentials";
import GeneralHtmlSupport from "@ckeditor/ckeditor5-html-support/src/generalhtmlsupport";
import Bold from "@ckeditor/ckeditor5-basic-styles/src/bold";
import Italic from "@ckeditor/ckeditor5-basic-styles/src/italic";
import Underline from "@ckeditor/ckeditor5-basic-styles/src/underline";
import Strikethrough from "@ckeditor/ckeditor5-basic-styles/src/strikethrough";
import BlockQuote from "@ckeditor/ckeditor5-block-quote/src/blockquote";
import Link from "@ckeditor/ckeditor5-link/src/link";
import List from "@ckeditor/ckeditor5-list/src/list";

/**
 * All the core plugins
 *
 * @returns { object } - the core plugins
 */
export function getCorePlugins() {
    return [Essentials, Bold, Italic, Underline, Strikethrough, Link, List, BlockQuote, GeneralHtmlSupport];
}
