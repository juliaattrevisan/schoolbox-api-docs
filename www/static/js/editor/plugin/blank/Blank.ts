/**
 * The constructor of the Blank plugin, used to hide the selected text for a Quiz cloze question
 * So when presented in a quiz the text is hidden, and the user must fill in the blank
 * For more information on plugins, see https://ckeditor.com/docs/ckeditor5/latest/installation/plugins/plugins.html
 */

import { Plugin } from "@ckeditor/ckeditor5-core";
import BlankUI from "./BlankUI";
import BlankEditing from "./BlankEditing";

export const MAKE_BLANK = "makeBlank";

export default class Blank extends Plugin {
    static get requires() {
        return [BlankUI, BlankEditing];
    }
}
