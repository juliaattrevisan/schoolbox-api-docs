/**
 * Returns the plugins that all editor type instances use
 */

import {
    Essentials,
    Bold,
    Italic,
    Underline,
    Strikethrough,
    Link,
    List,
    BlockQuote,
    GeneralHtmlSupport,
    PasteFromOffice,
    Notification,
} from "ckeditor5";
import { PasteFromOfficeEnhanced } from "ckeditor5-premium-features";
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
    | typeof List
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
        List,
        BlockQuote,
        GeneralHtmlSupport,
        Legacy,
        Notification,
        PasteFromOffice,
        PasteFromOfficeEnhanced,
    ];
}
