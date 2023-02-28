/**
 * Returns the data attributes that are used to play embedded audio/video
 *
 * @returns { string[] } The data attributes
 */
export function getDataAttributes() {
    return [
        "data-schoolboxresource-type",
        "data-schoolboxresource-filesystem",
        "data-schoolboxresource-thumbnail",
        "data-schoolboxresource-source",
        "data-schoolboxresource-width",
        "data-schoolboxresource-height",
        "data-schoolboxresource-fullwidth",
    ];
}
