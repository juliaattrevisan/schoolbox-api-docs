/**
 * The @phudak/ckeditor5-emoji plugin does not have a typescript definition file
 * as such, we need to create one to avoid typescript errors in the editor
 *
 * This module provides very basic types for each of the classes in the plugin, it
 * should not be taken as a definitive type definition for the plugin
 */

/* eslint-disable-next-line max-classes-per-file -- overriding as this needs to mimic the plugin */
declare module "@phudak/ckeditor5-emoji/src" {
    import { Plugin } from "ckeditor5";

    export class Emoji extends Plugin {}
    export class EmojiActivity extends Plugin {}
    export class EmojiFlags extends Plugin {}
    export class EmojiFood extends Plugin {}
    export class EmojiNature extends Plugin {}
    export class EmojiObjects extends Plugin {}
    export class EmojiPeople extends Plugin {}
    export class EmojiPlaces extends Plugin {}
    export class EmojiSymbols extends Plugin {}
}
