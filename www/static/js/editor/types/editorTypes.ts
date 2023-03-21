/**
 * The different types of toolbars that can be used for the editor
 */
export enum EditorToolbar {
    FULL = "full",
    COMMENT = "comment",
    SIMPLE = "simple",
}

/**
 * The config object for the editor
 */
export interface EditorConfig {
    targetElements: string[]; // the elements to target for the editor
    toolbar?: EditorToolbar.FULL | EditorToolbar.COMMENT | EditorToolbar.SIMPLE; // the toolbar to use for the editor
    hasTokens?: boolean; // whether or not to enable the tokens plugin
    tokenList?: string[]; // the list of tokens to use for the ckeditor
    baseHref?: string; // the base url to be prepended to relative links or assets included in the ckeditor
    addToToolbar?: string[]; // the list of extra plugins to add to the ckeditor outside of the scope of the EditorToolbar options
    removeFromToolbar?: string[]; // the list of plugins to remove from the ckeditor outside of the scope of the EditorToolbar options
    maxCharCount?: number; // the maximum number of characters allowed in the editor
}
