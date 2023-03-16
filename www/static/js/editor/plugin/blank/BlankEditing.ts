/**
 *
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import MakeBlankCommand from "./MakeBlankCommand";

export default class BlankEditing extends Plugin {
    /**
     * Runs on startup, update the schema, define the converters
     * and add the command that will run when the button in BlankUI is selected
     */
    init() {
        this.updateSchema();
        this.defineConverters();
        this.editor.commands.add("makeBlank", new MakeBlankCommand(this.editor));
    }

    /**
     * Add the additional element to the schema to allow the make blank command to work
     * https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_model_schema-Schema.html
     */
    updateSchema() {
        const schema = this.editor.model.schema;
        schema.register("blank", {
            inheritAllFrom: "$inlineObject",
        });
    }

    /**
     * Define the converters for the extra element, so that they are translated from the model to the view (downcast) and vice versa (upcast)
     * https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_conversion_conversion-Conversion.html
     */
    defineConverters() {
        const conversion = this.editor.conversion;
        conversion.for("downcast").elementToElement({
            model: "blank",
            view: {
                name: "span",
                classes: "selected-word",
            },
        });
        conversion.for("upcast").elementToElement({
            view: {
                name: "span",
                classes: "selected-word",
            },
            model: "blank",
        });
    }
}
