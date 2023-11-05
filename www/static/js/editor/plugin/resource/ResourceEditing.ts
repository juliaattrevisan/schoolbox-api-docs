/**
 * To extend imageBlock and imageInline to allow for additional data attributes
 * and add the insertResource command to the editor
 * @todo - there's currently an issue with preserving extra attributes on the imageBlock element
 * as imageInline appears to work fine, all images are currently set to imageInline, so all imageBlock
 * related functionality is currently disabled
 */

import { Plugin } from "@ckeditor/ckeditor5-core";
import type { ResizeImageCommand } from "@ckeditor/ckeditor5-image";
import type { Element } from "@ckeditor/ckeditor5-engine";
import InsertResourceCommand from "./InsertResourceCommand";
import { ResourceType, getExtraAttributes } from "./resourceUtils";

export default class ResourceEditing extends Plugin {
    /**
     * Runs on startup, defines the schema and converters
     */
    init() {
        this.updateSchema();
        this.defineConverters();
        this.onImageResized();
        this.editor.commands.add("insertResource", new InsertResourceCommand(this.editor));
    }

    /**
     * Add the additional data attributes we need for audio/video into the schema
     * https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_model_schema-Schema.html
     * Otherwise the attributes will be stripped out when the editor is loaded
     */
    updateSchema() {
        const { schema } = this.editor.model;
        // schema.extend("imageBlock", {
        //     allowAttributes: [...getExtraAttributes()],
        // });
        schema.extend("imageInline", {
            allowAttributes: [...getExtraAttributes()],
        });
    }

    /**
     * Define the converters for the extra attributes, so that they are translated from the model to the view (downcast) and vice versa (upcast)
     * https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_conversion_conversion-Conversion.html
     */
    defineConverters() {
        const { conversion } = this.editor;

        const attributes = getExtraAttributes();
        attributes.forEach((attribute) => {
            // conversion.for("downcast").attributeToAttribute({
            //     model: {
            //         name: "imageBlock",
            //         key: attribute,
            //     },
            //     view: {
            //         name: "img",
            //         key: attribute,
            //     },
            // });
            // conversion.for("upcast").attributeToAttribute({
            //     view: {
            //         name: "img",
            //         key: attribute,
            //     },
            //     model: {
            //         name: "imageBlock",
            //         key: attribute,
            //     },
            // });
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

    /**
     * When an image has been resized, we need to check if the image is linked to a video resource.
     * If it is we need to update the width and height custom attributes to match the new size.
     * This is so when the video is actually displayed, it's width/height is what's been set in the editor.
     * (If the image has been marked as full-width, these attributes will be ignored)
     */
    onImageResized() {
        const { editor } = this;
        const { model } = editor;
        const imageUtils = editor.plugins.get("ImageUtils");
        const resizeImageCommand = editor.commands.get("resizeImage") as ResizeImageCommand;
        resizeImageCommand.on("execute", (eventInfo) => {
            const imageElement = imageUtils.getClosestSelectedImageElement(model.document.selection) as Element;
            if (
                imageElement.hasAttribute("data-schoolboxresource-type") &&
                imageElement.getAttribute("data-schoolboxresource-type") === ResourceType.VIDEO
            ) {
                model.change((writer) => {
                    const width = eventInfo.source.value?.width || 640;
                    writer.setAttributes(
                        {
                            "data-schoolboxresource-width": parseInt(width),
                            "data-schoolboxresource-height": parseInt(width) * (9 / 16),
                        },
                        imageElement
                    );
                });
            }
        });
    }
}
