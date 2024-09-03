/**
 * The Full width button, setting up the button to be displayed in the toolbar
 * and to trigger the toggleFullWidth command on selection
 */

import { Plugin } from "@ckeditor/ckeditor5-core";
import { ButtonView } from "@ckeditor/ckeditor5-ui";
import ToggleFullWidthCommand from "./ToggleFullWidthCommand";

export default class FullWidthUI extends Plugin {
    /**
     * Register the button in the editor's UI component factory.
     */
    init(): void {
        this.editor.ui.componentFactory.add("fullWidth", () => {
            // Create the button and bind it to the command
            const icon =
                '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path opacity=".5" d="M2 3h16v1.5H2zm0 12h16v1.5H2z"/><path d="M18 7v5.5a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1zm-1.505.5H3.504V12h12.991V7.5z"/></svg>';
            const command = this.editor.commands.get("toggleFullWidth") as ToggleFullWidthCommand;
            const button = new ButtonView();
            button.label = "Full Width";
            button.tooltip = true;
            button.withText = true;
            button.icon = icon;
            button.on("execute", () => this.editor.execute("toggleFullWidth"));
            // highlight whether the button is on or off (adds .ck-on class to button)
            button.bind("isOn").to(command);
            // don't allow the button to be selected if the command is disabled
            button.bind("isEnabled").to(command);
            return button;
        });
    }
}
