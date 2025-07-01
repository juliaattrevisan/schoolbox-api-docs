import type { ClassicEditor, InlineEditor } from "ckeditor5";

/**
 * The different types of editors that can be used
 */
export enum EditorType {
    CLASSIC = "classic",
    INLINE = "inline",
}

/**
 * The different types of toolbars that can be used for the editor
 */
export enum EditorToolbar {
    DEFAULT = "default",
    COMMENT = "comment",
    COMMENT_AI = "comment_ai",
    SIMPLE = "simple",
}

/**
 * The config object for the editor
 */
export interface SbxEditorConfig {
    targetElements: (string | SbxEditorElement)[]; // the elements to target for the editor
    type: EditorType.CLASSIC | EditorType.INLINE; // the type of editor to use
    toolbar: EditorToolbar; // the toolbar to use for the editor
    hasTokens?: boolean; // whether or not to enable the tokens plugin
    tokenList?: string[]; // the list of tokens to use for the ckeditor
    baseHref?: string; // the base url to be prepended to relative links or assets included in the ckeditor
    addToToolbar?: string[]; // the list of extra plugins to add to the ckeditor outside of the scope of the EditorToolbar options
    removeFromToolbar?: string[]; // the list of plugins to remove from the ckeditor outside of the scope of the EditorToolbar options
    maxCharCount: number; // The maximum number of bytes allowed in the editor.  For ASCII text, 1 byte = 1 character.  Unicode characters can be multi-byte.
    addMaxCharCountDefaultEventListeners?: boolean; // Whether to add the default event listeners for dealing with events relating to max char count.
    showWordCount?: boolean; // whether or not to show the word count
    fileBrowserCategoryId?: number; // to traverse directly to a specific folder in the Resources section of the resource modal
    mirrorChangeEvents?: boolean; // whether or not to mirror the change events back to the source element
}

/**
 * The optional fields for the editor config that are given default values if not provided
 */
interface OptionalSbxEditorConfigFields {
    type?: SbxEditorConfig["type"];
    toolbar?: SbxEditorConfig["toolbar"];
    maxCharCount?: SbxEditorConfig["maxCharCount"];
}

/**
 * The minimum config that must be provided when initialising the editor
 */
export type PartialSbxEditorConfig = Omit<SbxEditorConfig, keyof OptionalSbxEditorConfigFields> &
    OptionalSbxEditorConfigFields;

export type SbxEditor = ClassicEditor | InlineEditor;

export const allowedEditorElements = ["TEXTAREA", "DIV", "ARTICLE"];
export type SbxEditorElement = HTMLTextAreaElement | HTMLDivElement; // | HTMLElement;
