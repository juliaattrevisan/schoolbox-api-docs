/**
 * The Blank button, setting up the button to be displayed in the toolbar
 * and to trigger the makeBlank command on selection
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import ButtonView from "@ckeditor/ckeditor5-ui/src/button/buttonview";
import fontBackgroundColorIcon from "@ckeditor/ckeditor5-font/theme/icons/font-background.svg";
import { MAKE_BLANK } from "./Blank";

export default class BlankUI extends Plugin {
    /**
     * Register the button in the editor's UI component factory.
     */
    init() {
        const editor = this.editor;
        this.editor.ui.componentFactory.add("blank", () => {
            // Create the button and bind it to the command
            const command = editor.commands.get(MAKE_BLANK);
            const button = new ButtonView();
            button.label = "Make Blank";
            button.tooltip = true;
            button.withText = false;
            button.icon = fontBackgroundColorIcon;
            button.class = "ck-button__schoolboxmakeblank";
            button.isToggable = true;
            button.on("execute", () => editor.execute(MAKE_BLANK));
            // bind isOn and isEnabled to the values in the command - determined by AttributeCommand
            button.bind("isOn", "isEnabled").to(command, "value", "isEnabled");
            return button;
        });
    }
}
