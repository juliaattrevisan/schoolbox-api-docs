/**
 * The constructor of the ResourceLink plugin, used to open up the resource picker
 * and insert the selected resource into the editor as a link
 * For more information on plugins, see https://ckeditor.com/docs/ckeditor5/latest/installation/plugins/plugins.html
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import ResourceLinkUI from "./ResourceLinkUI";
import ResourceLinkEditing from "./ResourceLinkEditing";

export default class ResourceLink extends Plugin {
    static get requires() {
        return [ResourceLinkUI, ResourceLinkEditing];
    }
}
