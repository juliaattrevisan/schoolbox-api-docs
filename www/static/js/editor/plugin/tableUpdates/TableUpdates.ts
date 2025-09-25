/**
 * Custom tweaks to the ckeditor table plugin, primarily to bring some CKEditor5 behaviour
 * back inline with CKEditor4, which our users are used to.
 */

import {
    Plugin,
    type DowncastInsertEvent,
    type Element,
    type ViewElement,
    type InsertTableCommand,
    type TableAlignmentCommand,
} from "ckeditor5";

export default class TableUpdates extends Plugin {
    isCK5Attribute = "data-is-ckeditor5-table";

    /**
     * Run when the plugin is initialized
     */
    init(): void {
        this.updateSchema();
        this.defineConverters();
        this.addCustomClass();
        this.addDefaultProperties();
        this.markAlignedTables();
    }

    /**
     * Add the additional is new attribute to the schema so we can add .sb-ck-table on new tables only
     * https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_model_schema-Schema.html
     * Otherwise the attributes will be stripped out when the editor is loaded
     */
    updateSchema(): void {
        this.editor.model.schema.extend("table", {
            allowAttributes: [this.isCK5Attribute],
        });
    }

    /**
     * Define the converters for the extra attributes, so that they are translated from the model to the view (downcast) and vice versa (upcast)
     * https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_conversion_conversion-Conversion.html
     */
    defineConverters(): void {
        const { conversion } = this.editor;
        conversion.for("downcast").attributeToAttribute({
            model: {
                name: "table",
                key: this.isCK5Attribute,
            },
            view: {
                // @ts-expect-error -- This does match one of the types, but it doesn't quite seem to be able to infer that at the moment...
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
                value: undefined,
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
    addCustomClass(): void {
        this.editor.conversion.for("downcast").add((dispatcher) => {
            dispatcher.on<DowncastInsertEvent>(
                "insert:table",
                (event, data, conversionApi) => {
                    const viewWriter = conversionApi.writer;
                    const modelTable = data.item as Element;
                    const viewElement = conversionApi.mapper.toViewElement(modelTable) as ViewElement;
                    const viewTable = viewElement.is("element", "table")
                        ? viewElement
                        : Array.from(viewElement.getChildren()).find((viewChild) => viewChild.is("element", "table"));
                    if (modelTable.hasAttribute(this.isCK5Attribute) && viewTable !== undefined) {
                        viewWriter.addClass("sb-ck-table", viewTable as ViewElement);
                    }
                },
                { priority: "low" },
            );
        });
    }

    /**
     * Get the currently selected table
     * @returns The currently selected table
     */
    getSelectedTable(): Element | null | undefined {
        const { selection } = this.editor.model.document;
        const table = selection.getFirstPosition()?.findAncestor("table");
        return table;
    }

    /**
     * Add the following properties as default to newly created tables, columns and rows
     */
    addDefaultProperties(): void {
        const insertTableCommand = this.editor.commands.get("insertTable") as InsertTableCommand;
        insertTableCommand.on("execute", () => {
            this.editor.execute("tableWidth", {
                value: "100%",
            });
            this.editor.execute("tableAlignment", {
                value: "left",
            });
            this.editor.model.change((writer) => {
                // add the isCK5Attribute attribute to the table so we can add .sb-ck-table to the table in the downcast
                const selectedTable = this.getSelectedTable();
                if (!selectedTable) return;
                writer.setAttribute(this.isCK5Attribute, "true", selectedTable);
            });
        });
    }

    /**
     * Add the isNew attribute to a table when the tableAlignment command is executed
     * This is so an old table created in CKEditor4 can still be centre aligned if desired
     */
    markAlignedTables(): void {
        const tableAlignmentCommand = this.editor.commands.get("tableAlignment") as TableAlignmentCommand;
        tableAlignmentCommand.on("execute", () => {
            this.editor.model.change((writer) => {
                const selectedTable = this.getSelectedTable();
                if (!selectedTable) return;
                writer.setAttribute(this.isCK5Attribute, "true", selectedTable);
            });
        });
    }
}
