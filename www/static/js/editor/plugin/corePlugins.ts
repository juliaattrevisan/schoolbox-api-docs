/**
 * Returns the plugins that all editor type instances use
 */

import { Essentials } from "@ckeditor/ckeditor5-essentials";
import { Bold, Italic, Underline, Strikethrough } from "@ckeditor/ckeditor5-basic-styles";
import { List } from "@ckeditor/ckeditor5-list";
import { BlockQuote } from "@ckeditor/ckeditor5-block-quote";
import { GeneralHtmlSupport } from "@ckeditor/ckeditor5-html-support";
import { PasteFromOffice } from "@ckeditor/ckeditor5-paste-from-office";
import { Notification } from "@ckeditor/ckeditor5-ui";
import Link from "@js/editor/plugin/link/Link";
import Legacy from "@js/editor/plugin/legacy/Legacy";

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
        List,
        BlockQuote,
        GeneralHtmlSupport,
        Legacy,
        Notification,
        PasteFromOffice,
    ];
}
