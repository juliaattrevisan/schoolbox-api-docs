export interface EditorConfig {
    targetElements: string[]; // the elements to target for the editor
    hasTokens?: boolean; // whether or not to enable the tokens plugin
    isComment?: boolean; // show the comment toolbar
}
