/**
 * The constructor of the Resource plugin, used to open up the resource picker
 * and insert the selected resource into the editor
 * For more information on plugins, see https://ckeditor.com/docs/ckeditor5/latest/installation/plugins/plugins.html
 */

import { Plugin } from "@ckeditor/ckeditor5-core";
import ResourceUI from "./ResourceUI";
import ResourceEditing from "./ResourceEditing";
import AutoResource from "./AutoResource";

export default class Resource extends Plugin {
    static get requires(): (typeof ResourceUI | typeof ResourceEditing | typeof AutoResource)[] {
        return [ResourceUI, ResourceEditing, AutoResource];
    }
}
