/**
 * To extend imageBlock and imageInline to allow for additional data attributes
 * and add the toggleFullWidth command to the editor
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import ToggleFullWidthCommand from "./ToggleFullWidthCommand";

export default class FullWidthEditing extends Plugin {
    /**
     * Runs on startup, defines the schema and converters
     */
    init() {
        this.updateSchema();
        this.defineConverters();
        this.editor.commands.add("toggleFullWidth", new ToggleFullWidthCommand(this.editor));
    }

    /**
     * Add the additional data attributes we need for audio/video into the schema
     * https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_model_schema-Schema.html
     * Otherwise the attributes will be stripped out when the editor is loaded
     */
    updateSchema() {
        const schema = this.editor.model.schema;
        console.warn(schema);
        schema.extend("imageBlock", {
            allowAttributes: ["data-is-full-width"],
        });
        schema.extend("imageInline", {
            allowAttributes: ["data-is-full-width"],
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
                name: "imageBlock",
                key: "data-is-full-width",
            },
            view: {
                name: "img",
                key: "data-is-full-width",
            },
        });
        conversion.for("upcast").attributeToAttribute({
            view: {
                name: "img",
                key: "data-is-full-width",
            },
            model: {
                name: "imageBlock",
                key: "data-is-full-width",
            },
        });
        conversion.for("downcast").attributeToAttribute({
            model: {
                name: "imageInline",
                key: "data-is-full-width",
            },
            view: {
                name: "img",
                key: "data-is-full-width",
            },
        });
        conversion.for("upcast").attributeToAttribute({
            view: {
                name: "img",
                key: "data-is-full-width",
            },
            model: {
                name: "imageInline",
                key: "data-is-full-width",
            },
        });
    }
}
