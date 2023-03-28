/**
 * Add a class to all newly created tables so we can style them
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";

export default class TableClass extends Plugin {
    init() {
        const insertTableCommand = this.editor.commands.get("insertTable");
        insertTableCommand.on("execute", (event, table) => {
            this.editor.execute("tableBackgroundColor", {
                value: "#ff0",
            });
        });

        this.editor.model.schema.extend("table", {
            allowAttributes: ["class"],
        });
    }
}
