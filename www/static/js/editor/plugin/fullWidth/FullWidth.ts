/**
 * To enforce an image to be 100% width of the editor window
 */

import { Plugin } from "@ckeditor/ckeditor5-core";
import FullWidthUI from "./FullWidthUI";
import FullWidthEditing from "./FullWidthEditing";

export default class FullWidth extends Plugin {
    static get requires(): (typeof FullWidthUI | typeof FullWidthEditing)[] {
        return [FullWidthUI, FullWidthEditing];
    }
}
