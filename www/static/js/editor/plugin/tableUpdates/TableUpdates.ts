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
        this.addCustomClass();
        this.addDefaultProperties();
    }

    /**
     * Add a custom class to all tables so we can adjust their styles
     * in both the ckeditor and display view
     * As tables are "downcast" from the model, the "insert:table" event is fired for each
     * When this happens we ensure that we select the correct table element and add the class.
     * This solution is based on downcastTableResizedClass from:
     * https://github.com/ckeditor/ckeditor5/blob/92602e6fcf109c957cba2e764588906c726e2e6a/packages/ckeditor5-table/src/tablecolumnresize/converters.ts
     */
    addCustomClass() {
        this.editor.conversion.for("downcast").add((dispatcher) => {
            dispatcher.on(
                "insert:table",
                (event, data, conversionApi) => {
                    const viewWriter = conversionApi.writer;
                    const modelTable = data.item;
                    const viewElement: ViewElement = conversionApi.mapper.toViewElement(modelTable);
                    const viewTable = viewElement.is("element", "table")
                        ? viewElement
                        : Array.from(viewElement.getChildren()).find((viewChild) => viewChild.is("element", "table"));

                    viewWriter.addClass("sb-ck-table", viewTable);
                },
                { priority: "low" }
            );
        });
    }

    /**
     * Add the following properties as default to newly created tables
     */
    addDefaultProperties() {
        const insertTableCommand = this.editor.commands.get("insertTable");
        insertTableCommand.on("execute", () => {
            this.editor.execute("tableWidth", {
                value: "100%",
            });
            this.editor.execute("tableAlignment", {
                value: "left",
            });
            this.editor.execute("tableBorderStyle", {
                value: "solid",
            });
            this.editor.execute("tableBorderWidth", {
                value: "1px",
            });
            this.editor.execute("tableBorderColor", {
                value: "#b3b3b3",
            });
        });
    }
}
