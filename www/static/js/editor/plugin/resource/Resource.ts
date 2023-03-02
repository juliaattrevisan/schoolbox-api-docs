/**
 * The constructor of the Resource plugin, used to open up the resource picker
 * and insert the selected resource into the editor
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import ResourceUI from "./ResourceUI";
import ResourceEditing from "./ResourceEditing";

export default class Resource extends Plugin {
    static get requires() {
        return [ResourceUI, ResourceEditing];
    }
}
