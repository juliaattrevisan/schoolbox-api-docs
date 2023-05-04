/**
 * Custom tweaks to the ckeditor table plugin, primarily to bring some CKEditor5 bevaviour
 * back inline with CKEditor4, which our users are used to.
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";

export default class TableUpdates extends Plugin {
    /**
     * Run when the plugin is initialized
     */
    init() {
        this.addDefaultProperties();
    }

    /**
     * Add the following properties as default to newly created tables
     */
    addDefaultProperties() {
        const insertTableCommand = this.editor.commands.get("insertTable");
        insertTableCommand.on("execute", () => {
            // this.editor.execute("tableWidth", {
            //     value: "100%",
            // });
            // this.editor.execute("tableAlignment", {
            //     value: "left",
            // });
            // this.editor.execute("tableBorderStyle", {
            //     value: "solid",
            // });
            // this.editor.execute("tableBorderWidth", {
            //     value: "1px",
            // });
            // this.editor.execute("tableBorderColor", {
            //     value: "#b3b3b3",
            // });
            // this.editor.execute("tableCellBorderStyle", {
            //     value: "solid",
            // });
            // this.editor.execute("tableCellBorderWidth", {
            //     value: "1px",
            // });
            // this.editor.execute("tableCellBorderColor", {
            //     value: "#b3b3b3",
            // });
        });
    }
}
