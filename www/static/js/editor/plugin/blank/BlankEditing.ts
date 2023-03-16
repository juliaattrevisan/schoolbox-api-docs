/**
 * To allow the make blank command to work, we need to add the
 * additional attribute to the schema and define the converters.
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import AttributeCommand from "@ckeditor/ckeditor5-basic-styles/src/attributecommand";
import { MAKE_BLANK } from "./Blank";

export default class BlankEditing extends Plugin {
    /**
     * Runs on startup, update the schema, define the converters
     * and add the command that will run when the button in BlankUI is selected
     */
    init() {
        this.updateSchema();
        this.defineConverters();
        this.editor.commands.add(MAKE_BLANK, new AttributeCommand(this.editor, MAKE_BLANK));
    }

    /**
     * Add the additional attribute to the schema to allow the make blank command to work
     * https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_model_schema-Schema.html
     */
    updateSchema() {
        const schema = this.editor.model.schema;
        schema.extend("$text", { allowAttributes: MAKE_BLANK });
        schema.setAttributeProperties(MAKE_BLANK, {
            isFormatting: true, // Indicates that the attribute should be considered as a visual formatting, like bold, italic or fontSize
            copyOnEnter: false, // Indicates that given text attribute should be copied to the next block when enter is pressed.
        });
    }

    /**
     * Define the converters for the extra element, so that they are translated from the model to the view (downcast) and vice versa (upcast)
     * https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_conversion_conversion-Conversion.html
     */
    defineConverters() {
        const conversion = this.editor.conversion;
        conversion.for("downcast").attributeToElement({
            model: MAKE_BLANK,
            view: {
                name: "span",
                classes: "selected-word",
            },
        });
        conversion.for("upcast").elementToAttribute({
            view: {
                name: "span",
                classes: "selected-word",
            },
            model: MAKE_BLANK,
        });
    }
}
