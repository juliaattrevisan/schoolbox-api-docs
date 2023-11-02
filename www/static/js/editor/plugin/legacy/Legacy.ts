/**
 * As much as possible, allow features no longer available in CKEditor 5 to
 * at least still persist and beave the same way in display view.
 */

import { Plugin } from "@ckeditor/ckeditor5-core";

/**
 * Referenced from: https://github.com/ckeditor/ckeditor5/blob/96d53e7cfca358d54d4a154dd9f6fd7f5f2cc524/packages/ckeditor5-link/src/utils.js
 * @param { string } id - the id to use as the anchor link
 * @param { module:engine/conversion/downcastdispatcher~DowncastConversionApi } conversionApi - the conversion api
 * @returns { module:engine/view/attributeelement~AttributeElement } - the link element
 */
export function createLinkElement(id, { writer }) {
    const linkElement = writer.createAttributeElement("a", { id }, { priority: 5 });
    writer.setCustomProperty("link", true, linkElement);

    return linkElement;
}

/**
 * In CKEditor 4 there was an anchor button that allowed you to create an anchor,
 * it set an <a> tag with an id attribute. So although this button is no longer available
 * in CKEditor 5, we can still allow the id attribute on <a> tags.
 * So any existing anchors will still work
 *
 * @param { Editor } editor - the editor instance
 */
function allowExistingAnchors(editor) {
    // Allow id attribute on all inline nodes.
    editor.model.schema.extend("$text", { allowAttributes: "id" });
    editor.conversion.for("dataDowncast").attributeToElement({
        model: "id",
        view: createLinkElement,
    });
    editor.conversion.for("editingDowncast").attributeToElement({
        model: "id",
        view: (id, conversionApi) => {
            return createLinkElement(id, conversionApi);
        },
    });
    editor.conversion.for("upcast").elementToAttribute({
        view: {
            name: "a",
            attributes: {
                id: true,
            },
        },
        model: {
            key: "id",
            value: (viewElement) => viewElement.getAttribute("id"),
        },
    });
}

export default class Legacy extends Plugin {
    init() {
        allowExistingAnchors(this.editor);
    }
}
