/**
 *
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
// import ToggleFullWidthCommand from "./ToggleFullWidthCommand";

export default class BlankEditing extends Plugin {
    /**
     * Runs on startup, in this instance we only need to define the command
     * that will run when the button in BlankUI is selected
     */
    init() {
        this.updateSchema();
        this.defineConverters();
        // this.editor.commands.add("toggleFullWidth", new ToggleFullWidthCommand(this.editor));
    }

    /**
     * Add the additional data attributes we need for audio/video into the schema
     * https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_model_schema-Schema.html
     * Otherwise the attributes will be stripped out when the editor is loaded
     */
    updateSchema() {
        const schema = this.editor.model.schema;
        schema.register("blank", {
            inheritAllFrom: "$inlineObject",
        });
    }

    /**
     * Define the converters for the extra attributes, so that they are translated from the model to the view (downcast) and vice versa (upcast)
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
