/**
 * Various utility functions/helpers for the resource plugins
 */

import type { Editor } from "@ckeditor/ckeditor5-core";
import type { Element } from "@ckeditor/ckeditor5-engine";
import type { SbxEditorConfig } from "@js/editor/types/editorTypes";
import { getWithoutExtension } from "@js/util/fileUtils";

export interface AsyncUploadResponseError {
    success: false;
    error: number;
    message: string;
    hash: "";
}

export interface AsyncUploadResponseSuccess {
    success: true;
    error: 200;
    message: "OK";
    hash: string[];
    meta: {
        type: string | null;
        size: number;
        name: string;
        file: {
            name: string;
            hash: string;
            mime: string;
            type: ResourceType;
            size: number | null;
            _links: {
                fetch?: string;
                icon?: string;
                image?: string;
                stream?: string;
                poster?: string;
            };
        };
    };
}

// A response from /storage/asyncUpload.php
export type AsyncUploadResponse = AsyncUploadResponseError | AsyncUploadResponseSuccess;

/**
 * Get the url of the resource, and if we have specified a baseHref, to then use that
 * @param { string } url - the url to reformat
 * @param { SbxEditorConfig } config - the CKEditor instance config
 * @returns { URL|string } - the url with the baseHref conditionally added
 */
export function getURL(url: string, config: SbxEditorConfig): URL | string {
    // If we are requesting a baseHref to exist, we should be using it...
    if (config.baseHref) {
        return new URL(url, config.baseHref);
    }
    return url;
}

/**
 * Returns the extra data attributes that are used to play embedded audio/video
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
export enum ResourceType {
    IMAGE = "image",
    VIDEO = "video",
    AUDIO = "audio",
    FOLDER = "folder",
    DOCUMENT = "document",
    MEETING_URL = "meetingUrl",
    OTHER = "other",
}

/**
 * The resource object, detailing the options returned from the server
 */
export type Resource = {
    filesystem: string;
    hash: string;
    mime: string;
    name: string;
    type:
        | ResourceType.IMAGE
        | ResourceType.VIDEO
        | ResourceType.AUDIO
        | ResourceType.FOLDER
        | ResourceType.DOCUMENT
        | ResourceType.MEETING_URL
        | ResourceType.OTHER;
    id?: string; // id will only be available on an already existing resource
    default?: string; // used by CKEditor ImageUpload plugin to display a loaded image
};

/**
 * Get the image src
 * @param { Resource } resource - the resource
 * @param { SbxEditorConfig } config - the editor config
 * @returns { URL|string } - the image src
 */
function getImageSrc(resource: Resource, config: SbxEditorConfig): URL | string {
    return getURL(`/storage/image.php?hash=${resource.hash}`, config);
}

/**
 * Get the image attributes
 * @param { Resource } resource - the resource
 * @param { SbxEditorConfig } config - the editor config
 * @returns { object } - the image attributes
 */
function getImageAttributes(
    resource: Resource,
    config: SbxEditorConfig,
): {
    src: URL | string;
    alt: string;
    linkHref: URL | string;
} {
    return {
        src: getImageSrc(resource, config),
        alt: resource.name,
        linkHref: getImageSrc(resource, config),
    };
}

/**
 * Get the video src
 * @param { Resource } resource - the resource
 * @param { SbxEditorConfig } config - the editor config
 * @returns { URL | string } - the video src
 */
function getVideoSrc(resource: Resource, config: SbxEditorConfig): URL | string {
    const resourceId = resource.id ? resource.id : resource.hash;
    return getURL(`/cms/sendVideo.php?id=${resourceId}&type=${resource.filesystem}`, config);
}

/**
 * Get the video thumbnail src
 * @param { Resource } resource - the resource
 * @param { SbxEditorConfig } config - the editor config
 * @returns { URL | string } - the video thumbnail src
 */
function getVideoThumb(resource: Resource, config: SbxEditorConfig): URL | string {
    const resourceId = resource.id ? resource.id : resource.hash;
    const filesystemWithReplaces = resource.filesystem.replace("browse", "sbfs").replace("store", "storage");
    return getURL(`/cms/videoThumb.php?size=poster_letterbox&id=${resourceId}&type=${filesystemWithReplaces}`, config);
}

/**
 * Get the video attributes, with the src as a thumbnail of the loaded video or the
 * "video processing" image if the video is not yet ready
 * @param { Resource } resource - the resource
 * @param { SbxEditorConfig } config - the editor config
 * @returns { object } - the image attributes
 */
function getVideoAttributes(resource: Resource, config: SbxEditorConfig) {
    const thumbnail = getVideoThumb(resource, config);
    return {
        src: thumbnail,
        alt: resource.name,
        width: "640px",
        "data-schoolboxresource-type": resource.type,
        "data-schoolboxresource-filesystem": resource.filesystem,
        "data-schoolboxresource-thumbnail": thumbnail,
        "data-schoolboxresource-source": getVideoSrc(resource, config),
        "data-schoolboxresource-width": 640,
        "data-schoolboxresource-height": 360,
        "data-schoolboxresource-fullwidth": "false",
    };
}

/**
 * Get the audio src
 * @param { Resource } resource - the resource
 * @param { SbxEditorConfig } config - the editor config
 * @returns { URL | string } - the audio src
 */
function getAudioSrc(resource: Resource, config: SbxEditorConfig): URL | string {
    const resourceId = resource.id ? resource.id : resource.hash;
    return getURL(`/cms/sendVideo.php?id=${resourceId}&type=${resource.filesystem}`, config);
}

/**
 * Get the audio thumbnail src, which is just a generic speaker
 * @param { SbxEditorConfig } config - the editor config
 * @returns { URL | string } - the audio thumbnail src
 */
function getAudioThumb(config: SbxEditorConfig): URL | string {
    return getURL(
        `${window.cdnPrefix}/images/core/component/audio-thumbnail-processing.gif?v=${window.siteVersion}`,
        config,
    );
}

/**
 * Get the audio attributes, with the src just being of a speaker
 * @param { Resource } resource - the resource
 * @param { SbxEditorConfig } config - the editor config
 * @returns { object } - the audio attributes
 */
function getAudioAttributes(resource: Resource, config: SbxEditorConfig) {
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
 * @param { Resource } resource - the resource
 * @param { SbxEditorConfig } config - the editor config
 * @returns { object } - the document attributes
 */
function getDocumentAttributes(resource: Resource, config: SbxEditorConfig) {
    return {
        src: getURL(`/ajax/mime/${resource.mime}`, config),
        alt: resource.name,
        linkHref: getURL(`/storage/fetch.php?hash=${resource.hash}`, config),
        linkToggleDownloadable: "true",
        width: "24px",
    };
}

/**
 * Get the other attributes, which is a file type for "other" files...
 * @param { Resource } resource - the resource
 * @param { SbxEditorConfig } config - the editor config
 * @returns { object } - the document attributes
 */
function getOtherAttributes(resource: Resource, config: SbxEditorConfig) {
    return {
        src: getURL(`/ajax/mime/${resource.mime}`, config),
        alt: resource.name,
        linkHref: getURL(`/storage/fetch.php?hash=${resource.hash}`, config),
        linkToggleDownloadable: "true",
        width: "24px",
    };
}

/**
 * Get the folder src
 * @param { Resource } resource - the resource
 * @param { SbxEditorConfig } config - the editor config
 * @returns { URL | string } - the folder src
 */
function getFolderSrc(resource: Resource, config: SbxEditorConfig): URL | string {
    return getURL(`/homepage/${resource.id as string}`, config);
}

/**
 * Get the storage src
 * @param { Resource } resource - the resource
 * @param { SbxEditorConfig } config - the editor config
 * @returns { URL | string } - the storage src
 */
function getStorageSrc(resource: Resource, config: SbxEditorConfig): URL | string {
    return getURL(`/storage/fetch.php?hash=${resource.hash}`, config);
}

/**
 * Get the web dav src
 * @param { Resource } resource - the resource
 * @param { SbxEditorConfig } config - the editor config
 * @returns { URL | string } - the web dav src
 */
function getWebDavSrc(resource: Resource, config: SbxEditorConfig): URL | string {
    return getURL(`/cms/sendNetworkFile.php?id=${resource.hash}`, config);
}

/**
 * Get the file src
 * @param { Resource } resource - the resource
 * @param { SbxEditorConfig } config - the editor config
 * @returns { URL | string } - the file src
 */
function getFileSrc(resource: Resource, config: SbxEditorConfig): URL | string {
    return getURL(`/send.php?id=${resource.hash}`, config);
}

/**
 * Get the resource attributes
 * @param { Resource } resource - the resource
 * @param { SbxEditorConfig } config - the editor config
 * @returns { object } - the resource attributes
 */
export function getAttributes(resource: Resource, config: SbxEditorConfig) {
    if (resource.type === ResourceType.IMAGE) {
        return getImageAttributes(resource, config);
    }
    if (resource.type === ResourceType.VIDEO) {
        return getVideoAttributes(resource, config);
    }
    if (resource.type === ResourceType.AUDIO) {
        return getAudioAttributes(resource, config);
    }
    if (resource.type === ResourceType.DOCUMENT) {
        return getDocumentAttributes(resource, config);
    }
    if (resource.type === ResourceType.OTHER) {
        return getOtherAttributes(resource, config);
    }
    return {};
}

/**
 * Get the resource attributes
 * @param { Resource } resource - the resource
 * @param { SbxEditorConfig } config - the editor config
 * @returns { object } - the resource attributes
 */
export function getSrc(resource: Resource, config: SbxEditorConfig) {
    if (resource.type === ResourceType.IMAGE) {
        return getImageSrc(resource, config);
    }
    if (resource.type === ResourceType.VIDEO) {
        return getStorageSrc(resource, config);
    }
    if (resource.type === ResourceType.AUDIO) {
        return getStorageSrc(resource, config);
    }
    if (resource.type === ResourceType.FOLDER) {
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
    if (resource.type === ResourceType.MEETING_URL) {
        return resource.id;
    }
    // Default assumption (not sure where this manifests any more, sorry)
    return getFileSrc(resource, config);
}

/**
 * Add a resource into the editor
 * @param { Editor } editor - the ckeditor instance
 * @param { Resource } resource - the resource
 */
export function addResource(editor: Editor, resource: Resource) {
    const config = editor.config.get("sbConfig") as SbxEditorConfig;
    const imageUtils = editor.plugins.get("ImageUtils");
    editor.model.change((writer) => {
        const image = imageUtils.insertImage(
            getAttributes(resource, config),
            editor.model.document.selection.getFirstPosition(),
            "imageInline",
        ) as Element;
        writer.setSelection(image, "after"); // deselect the selected image, otherwise further actions will overwrite it
        if (resource.type === ResourceType.DOCUMENT || resource.type === ResourceType.OTHER) {
            // we get without extension for now to avoid a copy/paste issue in ckeditor
            // pasting with the extension will cause ckeditor to look to embed the file again
            editor.model.insertContent(
                writer.createText(`${getWithoutExtension(resource.name)} `, getAttributes(resource, config)),
            );
        }
    });
}
