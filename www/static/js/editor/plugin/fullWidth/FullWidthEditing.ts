/**
 * To extend imageBlock and imageInline to allow for additional data attributes
 * and add the toggleFullWidth command to the editor
 */

import { Plugin } from "@ckeditor/ckeditor5-core";
import ToggleFullWidthCommand from "./ToggleFullWidthCommand";

export default class FullWidthEditing extends Plugin {
    /**
     * Runs on startup, in this instance we only need to define the command
     * that will run when the button in FullWidthUI is selected
     */
    init(): void {
        this.editor.commands.add("toggleFullWidth", new ToggleFullWidthCommand(this.editor));
    }
}
