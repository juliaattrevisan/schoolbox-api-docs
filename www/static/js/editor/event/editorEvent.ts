/**
 * The different events that can be dispatched by the editor
 */
export enum EditorEvent {
    EDITOR__CREATE = "EDITOR__CREATE",
    EDITOR__CREATED = "EDITOR__CREATED",
    EDITOR__CHECK_DATA = "EDITOR__CHECK_DATA",
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
