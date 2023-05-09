/**
 * Custom tweaks to the ckeditor table plugin, primarily to bring some CKEditor5 bevaviour
 * back inline with CKEditor4, which our users are used to.
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";

export default class TableUpdates extends Plugin {
    isCK5Attribute = "data-is-ckeditor5-table";

    /**
     * Run when the plugin is initialized
     */
    init() {
        this.updateSchema();
        this.defineConverters();
        this.addCustomClass();
        this.addDefaultProperties();
    }

    /**
     * Add the additional is new attribute to the schema so we can add .sb-ck-table on new tables only
     * https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_model_schema-Schema.html
     * Otherwise the attributes will be stripped out when the editor is loaded
     */
    updateSchema() {
        this.editor.model.schema.extend("table", {
            allowAttributes: [this.isCK5Attribute],
        });
    }

    /**
     * Define the converters for the extra attributes, so that they are translated from the model to the view (downcast) and vice versa (upcast)
     * https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_conversion_conversion-Conversion.html
     */
    defineConverters() {
        const conversion = this.editor.conversion;
        conversion.for("downcast").attributeToAttribute({
            model: {
                name: "table",
                key: this.isCK5Attribute,
            },
            view: {
                name: "table",
                key: this.isCK5Attribute,
            },
        });
        conversion.for("upcast").attributeToAttribute({
            view: {
                name: /^(table|figure)$/,
                key: this.isCK5Attribute,
            },
            model: {
                name: "table",
                key: this.isCK5Attribute,
            },
        });
    }

    /**
     * Add a custom class to newly created table so we can adjust their styles
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
                    if (modelTable.hasAttribute(this.isCK5Attribute)) {
                        viewWriter.addClass("sb-ck-table", viewTable);
                    }
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
            /**
             * Set the default border style, width and colour for all cells in the table.
             * This is a bit more involved than setting the table styles above, as we
             * need to get the table element from the selection, then get all the cell
             * elements from the table, then set the attributes on each cell.
             */
            const selection = this.editor.model.document.selection;
            const table = selection.getFirstPosition().findAncestor("table");
            // get all the cell elements from the table, first getting each row, then each cell within each row
            const cellElements = Array.from(table.getChildren())
                .filter((row) => row.name === "tableRow")
                .map((row) => Array.from(row.getChildren()).filter((cell) => cell.name === "tableCell"))
                .flat();
            this.editor.model.change((writer) => {
                // add the isNew attribute to the table so we can add .sb-ck-table to the table in the downcast
                writer.setAttribute(this.isCK5Attribute, "true", table);
                cellElements.forEach((cell) => {
                    writer.setAttribute("tableCellBorderStyle", "solid", cell);
                    writer.setAttribute("tableCellBorderWidth", "1px", cell);
                    writer.setAttribute("tableCellBorderColor", "#b3b3b3", cell);
                });
            });
        });
    }
}
