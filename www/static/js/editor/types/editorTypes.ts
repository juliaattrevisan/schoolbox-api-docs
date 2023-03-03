interface EditorConfig {
    targetElements: string[]; // the elements to target for the editor
}

export interface EditorDefaultConfig extends EditorConfig {
    hasTokens?: boolean; // whether or not to enable the tokens plugin
}
