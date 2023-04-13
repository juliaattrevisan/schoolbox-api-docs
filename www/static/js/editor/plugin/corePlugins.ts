/**
 * Returns the plugins that all editor type instances use
 */

import Essentials from "@ckeditor/ckeditor5-essentials/src/essentials";
import Bold from "@ckeditor/ckeditor5-basic-styles/src/bold";
import Italic from "@ckeditor/ckeditor5-basic-styles/src/italic";
import Underline from "@ckeditor/ckeditor5-basic-styles/src/underline";
import Strikethrough from "@ckeditor/ckeditor5-basic-styles/src/strikethrough";
import Link from "@js/editor/plugin/link/Link";
import List from "@ckeditor/ckeditor5-list/src/list";
import BlockQuote from "@ckeditor/ckeditor5-block-quote/src/blockquote";
import GeneralHtmlSupport from "@ckeditor/ckeditor5-html-support/src/generalhtmlsupport";
import Legacy from "@js/editor/plugin/legacy/Legacy";
import Notifcation from "@ckeditor/ckeditor5-ui/src/notification/notification";
import PasteFromOffice from "@ckeditor/ckeditor5-paste-from-office/src/pastefromoffice";

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
        Notifcation,
        PasteFromOffice,
    ];
}
