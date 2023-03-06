export interface EditorConfig {
    targetElements: string[]; // the elements to target for the editor
    hasTokens?: boolean; // whether or not to enable the tokens plugin
    isComment?: boolean; // show the comment toolbar
    tokenList?: string[]; // the list of tokens to use for the ckeditor
    baseHref?: string; // the base url to be prepended to relative links or assets included in the ckeditor
}
