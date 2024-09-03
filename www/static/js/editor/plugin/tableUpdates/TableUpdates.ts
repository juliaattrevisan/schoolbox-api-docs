/**
 * Custom tweaks to the ckeditor table plugin, primarily to bring some CKEditor5 behaviour
 * back inline with CKEditor4, which our users are used to.
 */

import { Plugin } from "@ckeditor/ckeditor5-core";
import type { DowncastInsertEvent, Element, ViewElement } from "@ckeditor/ckeditor5-engine";
import type { InsertTableCommand, TableAlignmentCommand } from "@ckeditor/ckeditor5-table";

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
            this.editor.execute("tableBorderStyle", {
                value: "solid",
            });
            this.editor.execute("tableBorderWidth", {
                value: "1px",
            });
            this.editor.execute("tableBorderColor", {
                value: "#b3b3b3",
            });
            this.editor.model.change((writer) => {
                // add the isCK5Attribute attribute to the table so we can add .sb-ck-table to the table in the downcast
                const selectedTable = this.getSelectedTable();
                if (!selectedTable) return;
                writer.setAttribute(this.isCK5Attribute, "true", selectedTable);
            });
            this.setCellBorders();
        });
        const insertColumnAndRowCommands = [
            "insertTableColumnLeft",
            "insertTableColumnRight",
            "insertTableRowAbove",
            "insertTableRowBelow",
        ];
        insertColumnAndRowCommands.forEach((command) => {
            this.editor.commands.get(command)!.on("execute", () => {
                const cells = this.getCellElements();
                // only add default styles if all cells have not been set to border style none
                const hasBorderStyles = cells.some(
                    (cell) =>
                        // @ts-expect-error -- accessing a private property is intentional here, for now...
                        // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
                        cell._attrs.has("tableCellBorderStyle") && cell._attrs.get("tableCellBorderStyle") !== "none",
                );
                if (hasBorderStyles) {
                    this.setCellBorders();
                }
            });
        });
    }

    /**
     * Get all the cell elements from the table, first getting each row,
     * then each cell within each row
     * @returns An array of all the cell elements in the table
     */
    getCellElements(): Element[] {
        const selectedTable = this.getSelectedTable();
        if (!selectedTable) return [];
        const cellElements = Array.from(selectedTable.getChildren())
            .filter((row) => (row as Element).name === "tableRow")
            .map((row) =>
                Array.from((row as Element).getChildren()).filter((cell) => (cell as Element).name === "tableCell"),
            )
            .flat();
        return cellElements as Element[];
    }

    /**
     * Set the default border style, width and colour for all cells in the table.
     * This is a bit more involved than setting the table styles above, as we
     * need to get the table element from the selection, then get all the cell
     * elements from the table, then set the attributes on each cell.
     */
    setCellBorders(): void {
        const cells = this.getCellElements();
        // @ts-expect-error -- accessing a private property is intentional here, for now...
        // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
        const filteredCells = cells.filter((cell) => !cell._attrs.has("tableCellBorderStyle")); // to update only newly inserted cells
        this.editor.model.change((writer) => {
            filteredCells.forEach((cell) => {
                writer.setAttribute("tableCellBorderStyle", "solid", cell);
                writer.setAttribute("tableCellBorderWidth", "1px", cell);
                writer.setAttribute("tableCellBorderColor", "#b3b3b3", cell);
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
