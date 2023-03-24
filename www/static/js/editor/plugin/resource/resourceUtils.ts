/**
 * Various utility functions/helpers for the resource plugins
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
 * The types of resources that can be returned from the server
 */
export enum ResourceTypes {
    IMAGE = "image",
    VIDEO = "video",
    AUDIO = "audio",
    FOLDER = "folder",
    DOCUMENT = "document",
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
    type:
        | ResourceTypes.IMAGE
        | ResourceTypes.VIDEO
        | ResourceTypes.AUDIO
        | ResourceTypes.FOLDER
        | ResourceTypes.DOCUMENT;
};

/**
 * Get the image src
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { string } - the image src
 */
function getImageSrc(resource: Resource, config: EditorConfig) {
    return getURL(`/storage/image.php?hash=${resource.hash}`, config);
}

/**
 * Get the image attributes
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { object } - the image attributes
 */
function getImageAttributes(resource: Resource, config: EditorConfig) {
    return {
        src: getImageSrc(resource, config),
        alt: resource.name,
    };
}

/**
 * Get the video src
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { string } - the video src
 */
function getVideoSrc(resource: Resource, config: EditorConfig) {
    return getURL(`/cms/sendVideo.php?id=${resource.id}&type=${resource.filesystem}`, config);
}

/**
 * Get the video thumbnail src
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { string } - the video thumbnail src
 */
function getVideoThumb(resource: Resource, config: EditorConfig) {
    const filesystemWithReplaces = resource.filesystem.replace("browse", "sbfs").replace("store", "storage");
    return getURL(`/cms/videoThumb.php?size=poster&id=${resource.id}&type=${filesystemWithReplaces}`, config);
}

/**
 * Get the video attributes, with the src as a thumbnail of the loaded video or the
 * "video processing" image if the video is not yet ready
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { object } - the image attributes
 */
function getVideoAttributes(resource: Resource, config: EditorConfig) {
    const thumbnail = getVideoThumb(resource, config);
    return {
        src: thumbnail,
        alt: resource.name,
        "data-schoolboxresource-type": resource.type,
        "data-schoolboxresource-filesystem": resource.filesystem,
        "data-schoolboxresource-thumbnail": thumbnail,
        "data-schoolboxresource-source": getVideoSrc(resource, config),
        "data-schoolboxresource-width": 512,
        "data-schoolboxresource-height": 288,
        "data-schoolboxresource-fullwidth": "false",
    };
}

/**
 * Get the audio src
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { string } - the audio src
 */
function getAudioSrc(resource: Resource, config: EditorConfig) {
    return getURL(`/cms/sendVideo.php?id=${resource.id}&type=${resource.filesystem}`, config);
}

/**
 * Get the audio thumbnail src, which is just a generic speaker
 *
 * @param { EditorConfig } config - the editor config
 * @returns { string } - the audio thumbnail src
 */
function getAudioThumb(config: EditorConfig) {
    return getURL(
        `${window.cdnPrefix}/images/core/component/audio-thumbnail-processing.gif?v=${window.siteVersion}`,
        config
    );
}

/**
 * Get the audio attributes, with the src just being of a speaker
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { object } - the audio attributes
 */
function getAudioAttributes(resource: Resource, config: EditorConfig) {
    const thumbnail = getAudioThumb(config);
    return {
        src: thumbnail,
        alt: resource.name,
        "data-schoolboxresource-type": resource.type,
        "data-schoolboxresource-filesystem": resource.filesystem,
        "data-schoolboxresource-thumbnail": thumbnail,
        "data-schoolboxresource-source": getAudioSrc(resource, config),
        "data-schoolboxresource-width": 64,
        "data-schoolboxresource-height": 64,
        "data-schoolboxresource-fullwidth": "false",
    };
}

/**
 * Get the document attributes
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { object } - the document attributes
 */
function getDocumentAttributes(resource: Resource, config: EditorConfig) {
    return {
        src: getURL(`/ajax/mime/${resource.mime}`, config),
        alt: resource.name,
        linkHref: getURL(`/storage/fetch.php?hash=${resource.hash}`, config),
    };
}

/**
 * Get the folder src
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { string } - the folder src
 */
function getFolderSrc(resource: Resource, config: EditorConfig) {
    return getURL(`/homepage/${resource.id}`, config);
}

/**
 * Get the storage src
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { string } - the storage src
 */
function getStorageSrc(resource: Resource, config: EditorConfig) {
    return getURL(`/storage/fetch.php?hash=${resource.hash}`, config);
}

/**
 * Get the web dav src
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { string } - the web dav src
 */
function getWebDavSrc(resource: Resource, config: EditorConfig) {
    return getURL(`/cms/sendNetworkFile.php?id=${resource.hash}`, config);
}

/**
 * Get the file src
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { string } - the file src
 */
function getFileSrc(resource: Resource, config: EditorConfig) {
    return getURL(`/send.php?id=${resource.hash}`, config);
}

/**
 * Get the resource attributes
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { object } - the resource attributes
 */
export function getAttributes(resource: Resource, config: EditorConfig) {
    if (resource.type === ResourceTypes.IMAGE) {
        return getImageAttributes(resource, config);
    }
    if (resource.type === ResourceTypes.VIDEO) {
        return getVideoAttributes(resource, config);
    }
    if (resource.type === ResourceTypes.AUDIO) {
        return getAudioAttributes(resource, config);
    }
    if (resource.type === ResourceTypes.DOCUMENT) {
        return getDocumentAttributes(resource, config);
    }
    return {};
}

/**
 * Get the resource attributes
 *
 * @param { Resource } resource - the resource
 * @param { EditorConfig } config - the editor config
 * @returns { object } - the resource attributes
 */
export function getSrc(resource: Resource, config: EditorConfig) {
    if (resource.type === ResourceTypes.IMAGE) {
        return getImageSrc(resource, config);
    }
    if (resource.type === ResourceTypes.VIDEO) {
        return getVideoSrc(resource, config);
    }
    if (resource.type === ResourceTypes.AUDIO) {
        return getAudioSrc(resource, config);
    }
    if (resource.type === ResourceTypes.FOLDER) {
        return getFolderSrc(resource, config);
    }
    // When you Upload A File via the modal's dropzone
    if (resource.filesystem === "store") {
        return getStorageSrc(resource, config);
    }
    // When you select a file via Resource Browser
    if (resource.filesystem === "browse") {
        return getStorageSrc(resource, config);
    }
    // When you select a file via Office365
    if (resource.filesystem === "office365") {
        return getStorageSrc(resource, config);
    }
    // When you select a My File
    if (resource.filesystem === "myfile") {
        return getStorageSrc(resource, config);
    }
    // When you select a WebDAV file
    if (resource.filesystem === "webdav") {
        return getWebDavSrc(resource, config);
    }
    // When you select a Microsoft Teams meeting url
    // normally empty, don't need to touch it
    if (resource.type === "meetingUrl") {
        return resource.id;
    }
    // Default assumption (not sure where this manifests anymore, sorry)
    return getFileSrc(resource, config);
}
