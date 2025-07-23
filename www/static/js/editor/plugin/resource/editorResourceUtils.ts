/**
 * CKEditor-specific utility functions/helpers for the resource plugins
 */

import type { Editor, Element } from "ckeditor5";
import { getWithoutExtension } from "@js/util/fileUtils";
import { getAttributes, ResourceType } from "@js/resource/resourceUtils";
import type { Resource } from "@js/resource/resourceUtils";
import type { SbxEditorConfig } from "@js/editor/types/editorTypes";

/**
 * Add a resource into the editor
 * @param editor - the ckeditor instance
 * @param resource - the resource
 */
export function addResource(editor: Editor, resource: Resource): void {
    const config = editor.config.get("sbConfig") as SbxEditorConfig;
    const imageUtils = editor.plugins.get("ImageUtils");
    editor.model.change((writer) => {
        const image = imageUtils.insertImage(
            getAttributes(resource, config.baseHref),
            editor.model.document.selection.getFirstPosition(),
            "imageInline",
        ) as Element;
        writer.setSelection(image, "after"); // deselect the selected image, otherwise further actions will overwrite it
        if (resource.type === ResourceType.DOCUMENT || resource.type === ResourceType.OTHER) {
            // we get without extension for now to avoid a copy/paste issue in ckeditor
            // pasting with the extension will cause ckeditor to look to embed the file again
            editor.model.insertContent(
                writer.createText(`${getWithoutExtension(resource.name)} `, getAttributes(resource, config.baseHref)),
            );
        }
    });
}
