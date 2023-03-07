/**
 * The Full width button, setting up the button to be displayed in the toolbar
 * and to trigger the toggleFullWidth command on selection
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import ButtonView from "@ckeditor/ckeditor5-ui/src/button/buttonview";

export default class FullWidthUI extends Plugin {
    /**
     * Register the button in the editor's UI component factory.
     */
    init() {
        this.editor.ui.componentFactory.add("fullWidth", () => {
            // Create the button and bind it to the command
            const command = this.editor.commands.get("toggleFullWidth");
            const button = new ButtonView();
            button.label = "Full width";
            button.tooltip = true;
            button.withText = true;
            button.on("execute", () => this.editor.execute("toggleFullWidth"));
            // highlight whether the button is on or off (adds .ck-on class to button)
            button.bind("isOn").to(command, "isOn");
            // don't allow the button to be selected if the command is disabled
            button.bind("isEnabled").to(command, "isEnabled");
            return button;
        });
    }
}
