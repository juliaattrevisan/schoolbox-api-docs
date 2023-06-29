import {ClassicEditor} from "@ckeditor/ckeditor5-build-classic";

/**
 * The different events that can be dispatched by the editor
 */
export enum EditorEvent {
    EDITOR__CREATE = "EDITOR__CREATE",
    EDITOR__CREATED = "EDITOR__CREATED",
    EDITOR__CHECK_DATA = "EDITOR__CHECK_DATA",
    EDITOR__DELETE = "EDITOR__DELETE",
    EDITOR__DELETED = "EDITOR__DELETED",

    /**
     * Fires following a change the textarea content when config.maxCharCount has been exceeded.
     */
    EDITOR__MAX_CHAR_COUNT_EXCEEDED = "EDITOR__MAX_CHAR_COUNT_EXCEEDED",

    /**
     * Fires following a change the textarea content if config.maxCharCount was exceeded on the most recent check,
     * but is no longer exceeded.
     */
    EDITOR__MAX_CHAR_COUNT_NO_LONGER_EXCEEDED = "EDITOR__MAX_CHAR_COUNT_NO_LONGER_EXCEEDED",
}

// Types for information objects sent with events. /////////////////////////////////////////////////////////////////////

/**
 * When an EDITOR__DELETE event is dispatched, include this info:
 * This tells interested parties that the editor included in the info is about to be deleted.
 */
export type EditorDeleteEventInfo = {
    editor: ClassicEditor,
    containingFormId: string,
    submitButtons: Array<HTMLInputElement|HTMLButtonElement>
};

/**
 * When an EDITOR__DELETED event is dispatched, include this info:
 * This tells interested parties that an editor has been deleted.
 */
export type EditorDeletedEventInfo = {
    keyOfDeletedEditor: string,
    containingFormId: string,
    submitButtons: Array<HTMLInputElement|HTMLButtonElement>
};
