/**
 * Returns the plugins that all editor type instances use
 */

import Essentials from "@ckeditor/ckeditor5-essentials/src/essentials";
import Bold from "@ckeditor/ckeditor5-basic-styles/src/bold";
import Italic from "@ckeditor/ckeditor5-basic-styles/src/italic";
import Underline from "@ckeditor/ckeditor5-basic-styles/src/underline";
import Strikethrough from "@ckeditor/ckeditor5-basic-styles/src/strikethrough";
// import Link from "@ckeditor/ckeditor5-link/src/link";
import Link from "@js/editor/plugin/link/Link";
import ResourceLink from "@js/editor/plugin/resourceLink/ResourceLink";
import List from "@ckeditor/ckeditor5-list/src/list";
import BlockQuote from "@ckeditor/ckeditor5-block-quote/src/blockquote";
import GeneralHtmlSupport from "@ckeditor/ckeditor5-html-support/src/generalhtmlsupport";
import Legacy from "@js/editor/plugin/Legacy";

/**
 * All the core plugins
 *
 * @returns { object } - the core plugins
 */
export function getCorePlugins() {
    return [
        Essentials,
        Bold,
        Italic,
        Underline,
        Strikethrough,
        Link,
        ResourceLink,
        List,
        BlockQuote,
        GeneralHtmlSupport,
        Legacy,
    ];
}
