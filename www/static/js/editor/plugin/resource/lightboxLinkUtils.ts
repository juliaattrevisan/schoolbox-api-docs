import type { Editor } from "ckeditor5";

export const LIGHTBOX_LINK_ATTRIBUTE = "linkLightboxRel";

type EditorWithSourceElement = Editor & {
    sourceElement?: HTMLElement;
};

/**
 * Returns the lightbox rel value for linked image resources.
 * @param editor - the ckeditor instance
 * @returns - the rel attribute value
 */
export function getLightboxRel(editor: Editor): string {
    return `lightbox[${(editor as EditorWithSourceElement).sourceElement?.id ?? editor.id}]`;
}
