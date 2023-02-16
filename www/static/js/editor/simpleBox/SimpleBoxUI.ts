import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import "./styles.css";

export default class SimpleBoxUI extends Plugin {
    init() {
        console.log("SimpleBoxUI#init() got called");
    }
}
