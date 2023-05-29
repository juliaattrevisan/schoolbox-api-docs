/**
 * The different types of toolbars that can be used for the editor
 */
export enum EditorToolbar {
    DEFAULT = "default",
    COMMENT = "comment",
    SIMPLE = "simple",
}

/**
 * The config object for the editor
 */
export interface EditorConfig {
    addToToolbar?: string[]; // the list of extra plugins to add to the ckeditor outside of the scope of the EditorToolbar options
    baseHref?: string; // the base url to be prepended to relative links or assets included in the ckeditor
    fileBrowserCategoryId?: number; // to traverse directly to a specific folder in the Resources section of the resource modal
    hasTokens?: boolean; // whether or not to enable the tokens plugin
    maxCharCount?: number; // The maximum number of bytes allowed in the editor.  For ASCII text, 1 byte = 1 character.  Unicode characters can be multi-byte.
    maxCharCountAddDefaultEventListeners?: boolean; // Whether to add the default event listeners for dealing with events relating to max char count.
    maxCharCountSubmitButtons?: HTMLElement[]; // array of submit buttons to disable/enable when the storage limit is exceeded/no-longer-exceeded.
    removeFromToolbar?: string[]; // the list of plugins to remove from the ckeditor outside of the scope of the EditorToolbar options
    showWordCount?: boolean; // whether or not to show the word count
    targetElements: string[]; // the elements to target for the editor
    tokenList?: string[]; // the list of tokens to use for the ckeditor
    toolbar?: EditorToolbar.DEFAULT | EditorToolbar.COMMENT | EditorToolbar.SIMPLE; // the toolbar to use for the editor
}
