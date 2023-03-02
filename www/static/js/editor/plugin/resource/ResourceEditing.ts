/**
 * To extend imageBlock and imageInline to allow for additional data attributes
 * and add the insertResource command to the editor
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import InsertResourceCommand from "./InsertResourceCommand";
import { getDataAttributes } from "./resourceConfig";

export default class ResourceEditing extends Plugin {
    /**
     * Runs on startup, defines the schema and converters
     */
    init() {
        this.updateSchema();
        this.defineConverters();
        this.editor.commands.add("insertResource", new InsertResourceCommand(this.editor));
    }

    /**
     * Add the additional data attributes we need for audio/video into the schema
     * https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_model_schema-Schema.html
     * Otherwise the attributes will be stripped out when the editor is loaded
     */
    updateSchema() {
        const schema = this.editor.model.schema;
        schema.extend("imageBlock", {
            allowAttributes: [...getDataAttributes()],
        });
        schema.extend("imageInline", {
            allowAttributes: [...getDataAttributes()],
        });
    }

    /**
     * Define the converters for the data attributes, so that they are translated from the model to the view (downcast) and vice versa (upcast)
     * https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_conversion_conversion-Conversion.html
     */
    defineConverters() {
        const conversion = this.editor.conversion;

        const dataAttributes = getDataAttributes();
        dataAttributes.forEach((attribute) => {
            conversion.for("downcast").attributeToAttribute({
                model: {
                    name: "imageBlock",
                    key: attribute,
                },
                view: {
                    name: "img",
                    key: attribute,
                },
            });
            conversion.for("upcast").attributeToAttribute({
                view: {
                    name: "img",
                    key: attribute,
                },
                model: {
                    name: "imageBlock",
                    key: attribute,
                },
            });
            conversion.for("downcast").attributeToAttribute({
                model: {
                    name: "imageInline",
                    key: attribute,
                },
                view: {
                    name: "img",
                    key: attribute,
                },
            });
            conversion.for("upcast").attributeToAttribute({
                view: {
                    name: "img",
                    key: attribute,
                },
                model: {
                    name: "imageInline",
                    key: attribute,
                },
            });
        });
    }
}
