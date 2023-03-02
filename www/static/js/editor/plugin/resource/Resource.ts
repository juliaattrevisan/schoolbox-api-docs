/**
 * The constructor of the Schoolbox Resource plugin
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import ResourceUI from "./ResourceUI";
import ResourceEditing from "./ResourceEditing";

export default class Resource extends Plugin {
    static get requires() {
        return [ResourceUI, ResourceEditing];
    }
}
