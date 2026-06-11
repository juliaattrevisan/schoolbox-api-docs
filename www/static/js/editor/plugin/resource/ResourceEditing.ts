/**
 * To extend imageInline to allow for additional data attributes
 * and add the insertResource command to the editor
 */

import { Plugin, type ResizeImageCommand, type Element, type ViewElement } from "ckeditor5";
import { ResourceType, getExtraAttributes } from "@js/resource/resourceUtils";
import InsertResourceCommand from "./InsertResourceCommand";

export const LIGHTBOX_LINK_ATTRIBUTE = "linkLightboxRel";
const LINK_ATTRIBUTE_PRIORITY = 5;

export default class ResourceEditing extends Plugin {
    /**
     * Runs on startup, defines the schema and converters
     */
    init(): void {
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
    updateSchema(): void {
        const { schema } = this.editor.model;
        schema.extend("imageInline", {
            allowAttributes: [...getExtraAttributes(), LIGHTBOX_LINK_ATTRIBUTE],
        });
    }

    /**
     * Define the converters for the extra attributes, so that they are translated from the model to the view (downcast) and vice versa (upcast)
     * https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_conversion_conversion-Conversion.html
     */
    defineConverters(): void {
        const { conversion } = this.editor;

        const attributes = getExtraAttributes();
        attributes.forEach((attribute) => {
            conversion.for("downcast").attributeToAttribute({
                model: {
                    name: "imageInline",
                    key: attribute,
                },
                view: {
                    // @ts-expect-error -- This does match one of the types, but it doesn't quite seem to be able to infer that at the moment...
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
                    value: undefined,
                },
            });
        });

        // define converters for the lightbox rel attribute, which is added to images when they are uploaded to trigger the lightbox gallery on the frontend
        conversion.for("downcast").attributeToElement({
            model: {
                name: "imageInline",
                key: LIGHTBOX_LINK_ATTRIBUTE,
            },
            view: (rel, { writer }, { item }: { item: Element }) => {
                if (!item.hasAttribute("linkHref")) {
                    return null;
                }
                const linkElement = writer.createAttributeElement(
                    "a",
                    { rel: String(rel) },
                    { priority: LINK_ATTRIBUTE_PRIORITY },
                );
                writer.setCustomProperty("link", true, linkElement);
                return linkElement;
            },
        });
        conversion.for("upcast").elementToAttribute({
            view: {
                name: "a",
                attributes: {
                    rel: /^lightbox\[.*\]$/,
                },
            },
            model: {
                key: LIGHTBOX_LINK_ATTRIBUTE,
                value: (viewElement: ViewElement) => viewElement.getAttribute("rel"),
            },
        });
    }

    /**
     * When an image has been resized, we need to check if the image is linked to a video resource.
     * If it is we need to update the width and height custom attributes to match the new size.
     * This is so when the video is actually displayed, it's width/height is what's been set in the editor.
     * (If the image has been marked as full-width, these attributes will be ignored)
     */
    onImageResized(): void {
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
                    const width = (eventInfo.source as ResizeImageCommand).value?.width || "640px";
                    writer.setAttributes(
                        {
                            "data-schoolboxresource-width": parseFloat(width),
                            "data-schoolboxresource-height": parseFloat(width) * (9 / 16),
                        },
                        imageElement,
                    );
                });
            }
        });
    }
}
