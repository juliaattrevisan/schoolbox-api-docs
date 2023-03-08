/**
 * To enforce an image to be 100% width of the editor window
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import FullWidthUI from "./FullWidthUI";
import FullWidthEditing from "./FullWidthEditing";

export default class FullWidth extends Plugin {
    static get requires() {
        return [FullWidthUI, FullWidthEditing];
    }
}
