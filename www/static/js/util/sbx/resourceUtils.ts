/**
 * Various utility functions/helpers for the resource plugins
 */

import type { Editor, Element } from "ckeditor5";
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
 * @param url - the url to reformat
 * @param baseHref - an option baseHref to add to the url
 * @returns - the url with the baseHref conditionally added
 */
export function getURL(url: string, baseHref?: string): URL | string {
    // If we are requesting a baseHref to exist, we should be using it...
    if (baseHref) {
        return new URL(url, baseHref);
    }
    return url;
}

/**
 * Returns the extra data attributes that are used to play embedded audio/video
 * @returns The data attributes
 */
export function getExtraAttributes(): string[] {
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
 * @param resource - the resource
 * @param baseHref - an option baseHref to add to the url
 * @returns - the image src
 */
function getImageSrc(resource: Resource, baseHref?: string): URL | string {
    return getURL(`/storage/image.php?hash=${resource.hash}`, baseHref);
}

/**
 * Get the image attributes
 * @param resource - the resource
 * @param baseHref - an option baseHref to add to the url
 * @returns - the image attributes
 */
function getImageAttributes(
    resource: Resource,
    baseHref?: string,
): {
    src: URL | string;
    alt: string;
    linkHref: URL | string;
} {
    return {
        src: getImageSrc(resource, baseHref),
        alt: resource.name,
        linkHref: getImageSrc(resource, baseHref),
    };
}

/**
 * Get the video src
 * @param resource - the resource
 * @param baseHref - an option baseHref to add to the url
 * @returns - the video src
 */
function getVideoSrc(resource: Resource, baseHref?: string): URL | string {
    const resourceId = resource.id ? resource.id : resource.hash;
    return getURL(`/cms/sendVideo.php?id=${resourceId}&type=${resource.filesystem}`, baseHref);
}

/**
 * Get the video thumbnail src
 * @param resource - the resource
 * @param baseHref - an option baseHref to add to the url
 * @returns - the video thumbnail src
 */
function getVideoThumb(resource: Resource, baseHref?: string): URL | string {
    const resourceId = resource.id ? resource.id : resource.hash;
    const filesystemWithReplaces = resource.filesystem.replace("browse", "sbfs").replace("store", "storage");
    return getURL(
        `/cms/videoThumb.php?size=poster_letterbox&id=${resourceId}&type=${filesystemWithReplaces}`,
        baseHref,
    );
}

/**
 * Get the video attributes, with the src as a thumbnail of the loaded video or the
 * "video processing" image if the video is not yet ready
 * @param resource - the resource
 * @param baseHref - an option baseHref to add to the url
 * @returns - the image attributes
 */
function getVideoAttributes(resource: Resource, baseHref?: string): Record<string, string | URL | number> {
    const thumbnail = getVideoThumb(resource, baseHref);
    return {
        src: thumbnail,
        alt: resource.name,
        width: "640px",
        "data-schoolboxresource-type": resource.type,
        "data-schoolboxresource-filesystem": resource.filesystem,
        "data-schoolboxresource-thumbnail": thumbnail,
        "data-schoolboxresource-source": getVideoSrc(resource, baseHref),
        "data-schoolboxresource-width": 640,
        "data-schoolboxresource-height": 360,
        "data-schoolboxresource-fullwidth": "false",
    };
}

/**
 * Get the audio src
 * @param resource - the resource
 * @param baseHref - an option baseHref to add to the url
 * @returns - the audio src
 */
function getAudioSrc(resource: Resource, baseHref?: string): URL | string {
    const resourceId = resource.id ? resource.id : resource.hash;
    return getURL(`/cms/sendVideo.php?id=${resourceId}&type=${resource.filesystem}`, baseHref);
}

/**
 * Get the audio thumbnail src, which is just a generic speaker
 * @param baseHref - an option baseHref to add to the url
 * @returns - the audio thumbnail src
 */
function getAudioThumb(baseHref?: string): URL | string {
    return getURL(
        `${window.cdnPrefix}/images/core/component/audio-thumbnail-processing.gif?v=${window.siteVersion}`,
        baseHref,
    );
}

/**
 * Get the audio attributes, with the src just being of a speaker
 * @param resource - the resource
 * @param baseHref - an option baseHref to add to the url
 * @returns - the audio attributes
 */
function getAudioAttributes(resource: Resource, baseHref?: string): Record<string, string | URL | number> {
    const thumbnail = getAudioThumb(baseHref);
    return {
        src: thumbnail,
        alt: resource.name,
        "data-schoolboxresource-type": resource.type,
        "data-schoolboxresource-filesystem": resource.filesystem,
        "data-schoolboxresource-thumbnail": thumbnail,
        "data-schoolboxresource-source": getAudioSrc(resource, baseHref),
        "data-schoolboxresource-width": 64,
        "data-schoolboxresource-height": 64,
        "data-schoolboxresource-fullwidth": "false",
    };
}

/**
 * Get the document attributes
 * @param resource - the resource
 * @param baseHref - an option baseHref to add to the url
 * @returns - the document attributes
 */
function getDocumentAttributes(resource: Resource, baseHref?: string): Record<string, string | URL> {
    return {
        src: getURL(`/ajax/mime/${resource.mime}`, baseHref),
        alt: resource.name,
        linkHref: getURL(`/storage/fetch.php?hash=${resource.hash}`, baseHref),
        linkToggleDownloadable: "true",
        width: "24px",
    };
}

/**
 * Get the other attributes, which is a file type for "other" files...
 * @param resource - the resource
 * @param baseHref - an option baseHref to add to the url
 * @returns - the document attributes
 */
function getOtherAttributes(resource: Resource, baseHref?: string): Record<string, string | URL> {
    return {
        src: getURL(`/ajax/mime/${resource.mime}`, baseHref),
        alt: resource.name,
        linkHref: getURL(`/storage/fetch.php?hash=${resource.hash}`, baseHref),
        linkToggleDownloadable: "true",
        width: "24px",
    };
}

/**
 * Get the folder src
 * @param resource - the resource
 * @param baseHref - an option baseHref to add to the url
 * @returns - the folder src
 */
function getFolderSrc(resource: Resource, baseHref?: string): URL | string {
    return getURL(`/homepage/${resource.id as string}`, baseHref);
}

/**
 * Get the storage src
 * @param resource - the resource
 * @param baseHref - an option baseHref to add to the url
 * @returns - the storage src
 */
function getStorageSrc(resource: Resource, baseHref?: string): URL | string {
    return getURL(`/storage/fetch.php?hash=${resource.hash}`, baseHref);
}

/**
 * Get the web dav src
 * @param resource - the resource
 * @param baseHref - an option baseHref to add to the url
 * @returns - the web dav src
 */
function getWebDavSrc(resource: Resource, baseHref?: string): URL | string {
    return getURL(`/cms/sendNetworkFile.php?id=${resource.hash}`, baseHref);
}

/**
 * Get the file src
 * @param resource - the resource
 * @param baseHref - an option baseHref to add to the url
 * @returns - the file src
 */
function getFileSrc(resource: Resource, baseHref?: string): URL | string {
    return getURL(`/send.php?id=${resource.hash}`, baseHref);
}

/**
 * Get the resource attributes
 * @param resource - the resource
 * @param baseHref - an option baseHref to add to the url
 * @returns - the resource attributes
 */
export function getAttributes(resource: Resource, baseHref?: string): Record<string, string | URL | number> {
    if (resource.type === ResourceType.IMAGE) {
        return getImageAttributes(resource, baseHref);
    }
    if (resource.type === ResourceType.VIDEO) {
        return getVideoAttributes(resource, baseHref);
    }
    if (resource.type === ResourceType.AUDIO) {
        return getAudioAttributes(resource, baseHref);
    }
    if (resource.type === ResourceType.DOCUMENT) {
        return getDocumentAttributes(resource, baseHref);
    }
    if (resource.type === ResourceType.OTHER) {
        return getOtherAttributes(resource, baseHref);
    }
    return {};
}

/**
 * Get the resource attributes
 * @param resource - the resource
 * @param baseHref - an option baseHref to add to the url
 * @returns - the resource attributes
 */
export function getSrc(resource: Resource, baseHref?: string): URL | string | undefined {
    if (resource.type === ResourceType.IMAGE) {
        return getImageSrc(resource, baseHref);
    }
    if (resource.type === ResourceType.VIDEO) {
        return getStorageSrc(resource, baseHref);
    }
    if (resource.type === ResourceType.AUDIO) {
        return getStorageSrc(resource, baseHref);
    }
    if (resource.type === ResourceType.FOLDER) {
        return getFolderSrc(resource, baseHref);
    }
    // When you Upload A File via the modal's dropzone
    if (resource.filesystem === "store") {
        return getStorageSrc(resource, baseHref);
    }
    // When you select a file via Resource Browser
    if (resource.filesystem === "browse") {
        return getStorageSrc(resource, baseHref);
    }
    // When you select a file via Office365
    if (resource.filesystem === "office365") {
        return getStorageSrc(resource, baseHref);
    }
    // When you select a My File
    if (resource.filesystem === "myfile") {
        return getStorageSrc(resource, baseHref);
    }
    // When you select a WebDAV file
    if (resource.filesystem === "webdav") {
        return getWebDavSrc(resource, baseHref);
    }
    // When you select a Microsoft Teams meeting url
    // normally empty, don't need to touch it
    if (resource.type === ResourceType.MEETING_URL) {
        return resource.id;
    }
    // Default assumption (not sure where this manifests any more, sorry)
    return getFileSrc(resource, baseHref);
}

/**
 * Add a resource into the editor
 * @param editor - the ckeditor instance
 * @param resource - the resource
 */
export function addResource(editor: Editor, resource: Resource): void {
    const config = editor.config.get("sbConfig") as SbxEditorConfig;
    const imageUtils = editor.plugins.get("ImageUtils");
    editor.model.change((writer) => {
        const image = imageUtils.insertImage(
            getAttributes(resource, config.baseHref),
            editor.model.document.selection.getFirstPosition(),
            "imageInline",
        ) as Element;
        writer.setSelection(image, "after"); // deselect the selected image, otherwise further actions will overwrite it
        if (resource.type === ResourceType.DOCUMENT || resource.type === ResourceType.OTHER) {
            // we get without extension for now to avoid a copy/paste issue in ckeditor
            // pasting with the extension will cause ckeditor to look to embed the file again
            editor.model.insertContent(
                writer.createText(`${getWithoutExtension(resource.name)} `, getAttributes(resource, config.baseHref)),
            );
        }
    });
}
