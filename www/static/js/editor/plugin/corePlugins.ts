/**
 * Returns the plugins that all editor type instances use
 */

import { Essentials } from "@ckeditor/ckeditor5-essentials";
import { Bold, Italic, Underline, Strikethrough } from "@ckeditor/ckeditor5-basic-styles";
import { DocumentList } from "@ckeditor/ckeditor5-list";
import { BlockQuote } from "@ckeditor/ckeditor5-block-quote";
import { GeneralHtmlSupport } from "@ckeditor/ckeditor5-html-support";
import { PasteFromOffice } from "@ckeditor/ckeditor5-paste-from-office";
import { PasteFromOfficeEnhanced } from "@ckeditor/ckeditor5-paste-from-office-enhanced";
import { Notification } from "@ckeditor/ckeditor5-ui";
import Link from "@js/editor/plugin/link/Link";
import Legacy from "@js/editor/plugin/legacy/Legacy";

/**
 * All the core plugins
 * @returns - the core plugins
 */
export function getCorePlugins(): (
    | typeof Essentials
    | typeof Bold
    | typeof Italic
    | typeof Underline
    | typeof Strikethrough
    | typeof Link
    | typeof DocumentList
    | typeof BlockQuote
    | typeof Legacy
    | typeof Notification
    | typeof PasteFromOfficeEnhanced
)[] {
    return [
        Essentials,
        Bold,
        Italic,
        Underline,
        Strikethrough,
        Link,
        DocumentList,
        BlockQuote,
        GeneralHtmlSupport,
        Legacy,
        Notification,
        PasteFromOffice,
        PasteFromOfficeEnhanced,
    ];
}
