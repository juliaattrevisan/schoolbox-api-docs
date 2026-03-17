/**
 * CKEditor-specific utility functions/helpers for the resource plugins
 */

import type { Editor, Element, Position, Writer } from "ckeditor5";
import { getWithoutExtension } from "@js/util/fileUtils";
import { getAttributes, ResourceType } from "@js/resource/resourceUtils";
import type { Resource } from "@js/resource/resourceUtils";
import type { SbxEditorConfig } from "@js/editor/types/editorTypes";

/**
 * Move the caret out of link context so the next insertion does not inherit the previous link.
 * CKEditor's link feature uses TwoStepCaretMovement for this when users arrow out of a link.
 * We mirror that behavior after inserting linked inline resources.
 * @param editor - the ckeditor instance
 * @param writer - the model writer used for the current change block
 */
export function exitLinkContext(editor: Editor, writer: Writer): void {
    const { selection } = editor.model.document;
    const linkAttributes = Array.from(selection.getAttributeKeys()).filter((attribute) => attribute.startsWith("link"));

    if (editor.plugins.has("TwoStepCaretMovement")) {
        editor.plugins.get("TwoStepCaretMovement")._handleForwardMovement();
        return;
    }

    writer.removeSelectionAttribute(linkAttributes);
}

/**
 * Insert a soft break after an inline resource when the schema allows it.
 * @param editor - the ckeditor instance
 * @param writer - the model writer used for the current change block
 * @param position - the insertion point after the inline resource
 */
export function insertSoftBreak(editor: Editor, writer: Writer, position: Position): void {
    if (!editor.model.schema.checkChild(position, "softBreak")) {
        return;
    }

    const softBreak = writer.createElement("softBreak");
    editor.model.insertContent(softBreak, position);
    writer.setSelection(softBreak, "after");
}

/**
 * Add a resource into the editor
 * @param editor - the ckeditor instance
 * @param resource - the resource
 */
export function addResource(editor: Editor, resource: Resource): void {
    const config = editor.config.get("sbConfig") as SbxEditorConfig;
    const imageUtils = editor.plugins.get("ImageUtils");
    const resourceAttributes = getAttributes(resource, config.baseHref);
    editor.model.change((writer) => {
        const image = imageUtils.insertImage(
            resourceAttributes,
            editor.model.document.selection.getFirstPosition(),
            "imageInline",
        ) as Element;
        writer.setSelection(image, "after"); // deselect the selected image, otherwise further actions will overwrite it
        if (resource.type === ResourceType.DOCUMENT || resource.type === ResourceType.OTHER) {
            // we get without extension for now to avoid a copy/paste issue in ckeditor
            // pasting with the extension will cause ckeditor to look to embed the file again
            const label = writer.createText(`${getWithoutExtension(resource.name)} `, resourceAttributes);
            editor.model.insertContent(label);
            writer.setSelection(label, "after");
        }

        if ("linkHref" in resourceAttributes) {
            exitLinkContext(editor, writer);
        }
    });
}
