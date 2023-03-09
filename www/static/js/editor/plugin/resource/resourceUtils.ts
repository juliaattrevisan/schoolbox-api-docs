/**
 * Various utility functions/helpers for the resource plugin
 */

import type { EditorConfig } from "@js/editor/types/editorTypes";

/**
 * Get the url of the resource, and if we have specified a baseHref, to then use that
 *
 * @param { string } url - the url to reformat
 * @param { EditorConfig } config - the CKEditor instance config
 * @returns { URL|string } - the url with the baseHref conditionally added
 */
export function getURL(url: string, config: EditorConfig) {
    // If we are requesting a baseHref to exist, we should be using it...
    if (config.baseHref) {
        return new URL(url, config.baseHref);
    }
    return url;
}

/**
 * Returns the extra data attributes that are used to play embedded audio/video
 *
 * @returns { string[] } The data attributes
 */
export function getExtraAttributes() {
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

/**
 * The resource object, detailing the options returned from the server
 */
export type Resource = {
    filesystem: string;
    hash: string;
    id: string;
    mime: string;
    name: string;
    type: string;
};

/**
 * Get the image attributes
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { object } - the image attributes
 */
export function getAttributes(resource: Resource, config: EditorConfig) {
    if (resource.type === "image") {
        return getImageAttributes(resource, config);
    }
    if (resource.type === "video") {
        return getVideoAttributes(resource, config);
    }
    if (resource.type === "audio") {
        return getAudioAttributes(resource, config);
    }
    return {};
}

/**
 * Image urls and regex
 * No thumbnails are used for images
 */
const image = {
    source(hash: string) {
        return `/storage/image.php?hash=${hash}`;
    },
    regex: /^\/storage\/image/,
};

/**
 * Get the image attributes
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { object } - the image attributes
 */
function getImageAttributes(resource: Resource, config: EditorConfig) {
    return {
        src: getURL(image.source(resource.hash), config),
        alt: resource.name,
    };
}

/**
 * Video urls and regex
 */
const video = {
    source(id: string, filesystem: string) {
        return `/cms/sendVideo.php?id=${id}&type=${filesystem}`;
    },
    thumbnail(id: string, filesystem: string) {
        /* Ridiculous quick fix
         */
        const filesystemWithReplaces = filesystem.replace("browse", "sbfs").replace("store", "storage");
        return `/cms/videoThumb.php?size=poster&id=${id}&type=${filesystemWithReplaces}`;
    },
    regex: /^\/cms\/sendVideo/,
};

/**
 * Get the video attributes, with the src as a thumbnail of the loaded video or the
 * "video processing" image if the video is not yet ready
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { object } - the image attributes
 */
function getVideoAttributes(resource: Resource, config: EditorConfig) {
    const thumbnail = getURL(video.thumbnail(resource.id, resource.filesystem), config);
    return {
        src: thumbnail,
        alt: resource.name,
        "data-schoolboxresource-type": resource.type,
        "data-schoolboxresource-filesystem": resource.filesystem,
        "data-schoolboxresource-thumbnail": thumbnail,
        "data-schoolboxresource-source": getURL(video.source(resource.id, resource.filesystem), config),
        "data-schoolboxresource-width": 512,
        "data-schoolboxresource-height": 288,
        "data-schoolboxresource-fullwidth": "false",
    };
}

/**
 * Audio urls and regex
 */
const audio = {
    source(id: string, filesystem: string) {
        return `/cms/sendVideo.php?id=${id}&type=${filesystem}`;
    },
    thumbnail: `${window.cdnPrefix}/images/core/component/audio-thumbnail-processing.gif?v=${window.siteVersion}`,
    regex: /^\/cms\/sendVideo/,
};

/**
 * Get the audio attributes, with the src just being of a speaker
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { object } - the audio attributes
 */
function getAudioAttributes(resource: Resource, config: EditorConfig) {
    const thumbnail = getURL(audio.thumbnail, config);
    return {
        src: thumbnail,
        alt: resource.name,
        "data-schoolboxresource-type": resource.type,
        "data-schoolboxresource-filesystem": resource.filesystem,
        "data-schoolboxresource-thumbnail": thumbnail,
        "data-schoolboxresource-source": getURL(audio.source(resource.id, resource.filesystem), config),
        "data-schoolboxresource-width": 64,
        "data-schoolboxresource-height": 64,
        "data-schoolboxresource-fullwidth": "false",
    };
}
