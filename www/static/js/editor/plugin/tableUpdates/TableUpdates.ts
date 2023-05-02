/**
 * Add a custom class to all tables so we can adjust their styles
 * in both the ckeditor and display view
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";

export default class TableUpdates extends Plugin {
    /**
     * As tables are "downcast" from the model, the "insert:table" event is fired for each
     * When this happens we ensure that we select the correct table element and add the class.
     * This solution is based on downcastTableResizedClass from:
     * https://github.com/ckeditor/ckeditor5/blob/92602e6fcf109c957cba2e764588906c726e2e6a/packages/ckeditor5-table/src/tablecolumnresize/converters.ts
     */
    init() {
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
}
