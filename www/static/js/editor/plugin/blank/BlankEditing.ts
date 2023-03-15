/**
 *
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
// import ToggleFullWidthCommand from "./ToggleFullWidthCommand";

export default class BlankEditing extends Plugin {
    /**
     * Runs on startup, in this instance we only need to define the command
     * that will run when the button in BlankUI is selected
     */
    init() {
        // this.editor.commands.add("toggleFullWidth", new ToggleFullWidthCommand(this.editor));
    }
}
