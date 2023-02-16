import SimpleBoxEditing from "./SimpleBoxEditing";
import SimpleBoxUI from "./SimpleBoxUI";
import Plugin from "@ckeditor/ckeditor5-core/src/plugin";

export default class SimpleBox extends Plugin {
    static get requires() {
        return [SimpleBoxEditing, SimpleBoxUI];
    }
}
