/**
 * The Blank button, setting up the button to be displayed in the toolbar
 * and to trigger the makeBlank command on selection
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import ButtonView from "@ckeditor/ckeditor5-ui/src/button/buttonview";

/**
 * Returns the button to display in the toolbar
 *
 * @returns { ButtonView } - the button to display in the toolbar
 */
function getButton() {
    const icon =
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M11.627 16.5zm5.873-.196zm0-7.001V8h-13v8.5h4.341c.191.54.457 1.044.785 1.5H2a1.5 1.5 0 0 1-1.5-1.5v-13A1.5 1.5 0 0 1 2 2h4.5a1.5 1.5 0 0 1 1.06.44L9.122 4H16a1.5 1.5 0 0 1 1.5 1.5v1A1.5 1.5 0 0 1 19 8v2.531a6.027 6.027 0 0 0-1.5-1.228zM16 6.5v-1H8.5l-2-2H2v13h1V8a1.5 1.5 0 0 1 1.5-1.5H16z"></path><path d="M14.5 19.5a5 5 0 1 1 0-10 5 5 0 0 1 0 10zM15 14v-2h-1v2h-2v1h2v2h1v-2h2v-1h-2z"></path></svg>';
    const button = new ButtonView();
    button.label = "Make Blank";
    button.tooltip = true;
    button.withText = false;
    button.icon = icon;
    button.class = "cke_button__schoolboxmakeblank";
    return button;
}

export default class ResourceUI extends Plugin {
    /**
     * Register the button in the editor's UI component factory.
     */
    init() {
        this.editor.ui.componentFactory.add("blank", () => {
            // Create the button and bind it to the command
            const button = getButton();
            button.on("execute", () => {
                console.warn("execute makeBlank");
            });
            // Disable the button when the command is disabled
            // const command = this.editor.commands.get("makeBlank");
            // button.bind("isOn", "isEnabled").to(command, "value", "isEnabled");
            return button;
        });
    }
}
