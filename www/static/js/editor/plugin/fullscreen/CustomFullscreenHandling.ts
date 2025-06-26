import { FullscreenEditing, Plugin, ClassicEditorHandler } from "ckeditor5";
import CustomEditorHandler from "./CustomEditorHandler";

export default class CustomFullscreenHandling extends Plugin {
    // Ensure 'toggleFullscreen' command, registered in `FullscreenEditing` plugin, is already available in the editor.
    public static get requires() {
        return [FullscreenEditing] as const;
    }

    init() {
        // Substitute the default editor handler with a custom one.
        this.editor.commands.get("toggleFullscreen").fullscreenHandler = new CustomEditorHandler(this.editor);
    }
}
