/**
 * Adds a button to the editor that allows the user to insert a token,
 * an alternative to using the @mention plugin inline
 */

import { Plugin } from "@ckeditor/ckeditor5-core";
import TokensUI from "./TokensUI";

export default class Tokens extends Plugin {
    static get requires() {
        return [TokensUI];
    }
}
