/**
 * The constructor of the Schoolbox Resource plugin
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import SchoolboxResourceUI from "./SchoolboxResourceUI";

export default class SchoolboxResource extends Plugin {
    static get requires() {
        return [SchoolboxResourceUI];
    }
}
