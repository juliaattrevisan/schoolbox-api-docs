import AddVideoEditing from "./AddVideoEditing";
import AddVideoUI from "./AddVideoUI";
import Plugin from "@ckeditor/ckeditor5-core/src/plugin";

export default class AddVideo extends Plugin {
    static get requires() {
        return [AddVideoEditing, AddVideoUI];
    }
}
