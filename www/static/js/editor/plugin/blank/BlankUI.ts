/**
 * The Blank button, setting up the button to be displayed in the toolbar
 * and to trigger the makeBlank command on selection
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import ButtonView from "@ckeditor/ckeditor5-ui/src/button/buttonview";
import fontBackgroundColorIcon from "@ckeditor/ckeditor5-font/theme/icons/font-background.svg";

export default class BlankUI extends Plugin {
    /**
     * Register the button in the editor's UI component factory.
     */
    init() {
        const editor = this.editor;
        this.editor.ui.componentFactory.add("blank", () => {
            // Create the button and bind it to the command
            const command = this.editor.commands.get("makeBlank");
            const button = new ButtonView();
            button.label = "Make Blank";
            button.tooltip = true;
            button.withText = false;
            button.icon = fontBackgroundColorIcon;
            button.class = "ck-button__schoolboxmakeblank";
            button.on("execute", () => this.editor.execute("makeBlank"));
            /// highlight whether the button is on or off (adds .ck-on class to button)
            // button.bind("isOn").to(command, "isOn");
            // don't allow the button to be selected if the command is disabled
            // button.bind("isEnabled").to(command, "isEnabled");
            return button;
        });
    }
}
