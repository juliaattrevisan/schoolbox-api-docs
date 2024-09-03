/**
 * To add the command to open the resource modal to the editor
 */

import { Plugin } from "@ckeditor/ckeditor5-core";
import InsertResourceLinkCommand from "./InsertResourceLinkCommand";

export default class ResourceLinkEditing extends Plugin {
    /**
     * Define the new command
     */
    init(): void {
        this.editor.commands.add("insertResourceLink", new InsertResourceLinkCommand(this.editor));
    }
}
