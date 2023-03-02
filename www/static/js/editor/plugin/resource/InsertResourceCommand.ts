/**
 * Handles the insertion of a resource into the editor
 */

import Command from "@ckeditor/ckeditor5-core/src/command";

const config = {
    url: {
        /* Find information about a node/resource
         */
        nodeinfo: "/editor/nodeInfo.php",
    },
    /* Video urls and regex
     */
    video: {
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
    },
    /* Audio urls and regex
     */
    audio: {
        source(id: string, filesystem: string) {
            return `/cms/sendVideo.php?id=${id}&type=${filesystem}`;
        },
        thumbnail: `${window.cdnPrefix}/images/core/component/audio-thumbnail-processing.gif?v=${window.siteVersion}`,
        regex: /^\/cms\/sendVideo/,
    },
    /* Image urls and regex
     * No thumbnails are used for images
     */
    image: {
        source(hash: string) {
            return `/storage/image.php?hash=${hash}`;
        },
        regex: /^\/storage\/image/,
    },

    /* ID of the default folder from which to start browsing resources
     */
    defaultFolderSelector: 0,
};

/**
 * Display the resource modal to select a file from
 */
function displayResourceModal() {
    $.fn.schoolboxResource({
        allowedFilesystems: "files",
        allowedFiles: "image/*,audio/*,video/*,.heic,.heics,.heif",
        useFolder: false,
        // defaultFolderSelector: defaultFolderSelector,
    });
}

type Resource = {
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
 * @returns { object } - the image attributes
 */
function getAttributes(resource: Resource) {
    if (resource.type === "image") {
        return getImageAttributes(resource);
    }
    if (resource.type === "video") {
        return getVideoAttributes(resource);
    }
    if (resource.type === "audio") {
        return getAudioAttributes(resource);
    }
    return {};
}

/**
 * Get the image attributes
 *
 * @param { Resource } resource - the resource
 * @returns { object } - the image attributes
 */
function getImageAttributes(resource: Resource) {
    return {
        src: config.image.source(resource.hash),
        alt: resource.name,
    };
}

/**
 * Get the video attributes, with the src as a thumbnail of the loaded video or the
 * "video processing" image if the video is not yet ready
 *
 * @param { Resource } resource - the resource
 * @returns { object } - the image attributes
 */
function getVideoAttributes(resource: Resource) {
    return {
        src: config.video.thumbnail(resource.id, resource.filesystem),
        alt: resource.name,
        "data-schoolboxresource-type": resource.type,
        "data-schoolboxresource-filesystem": resource.filesystem,
        "data-schoolboxresource-thumbnail": config.video.thumbnail(resource.id, resource.filesystem),
        "data-schoolboxresource-source": config.video.source(resource.id, resource.filesystem),
        "data-schoolboxresource-width": 512,
        "data-schoolboxresource-height": 288,
        "data-schoolboxresource-fullwidth": "true",
    };
}

/**
 * Get the audio attributes, with the src just being of a speaker
 *
 * @param { Resource } resource - the resource
 * @returns { object } - the audio attributes
 */
function getAudioAttributes(resource: Resource) {
    return {
        src: config.audio.thumbnail,
        alt: resource.name,
        "data-schoolboxresource-type": resource.type,
        "data-schoolboxresource-filesystem": resource.filesystem,
        "data-schoolboxresource-thumbnail": config.audio.thumbnail,
        "data-schoolboxresource-source": config.audio.source(resource.id, resource.filesystem),
        "data-schoolboxresource-width": 64,
        "data-schoolboxresource-height": 64,
        "data-schoolboxresource-fullwidth": "true",
    };
}

/**
 * Get the resource information from the server and
 * then insert the image/video/audio into the editor using the ImageUtils plugin.
 * Note that all three are inserted as images, but the extra attributes added to
 * audio/video will be used to display outside editing mode.
 *
 * See www/static/js/editor/jquery.proxy-replacement.js for how this occurs
 *
 * @param { Editor } editor - The editor instance
 * @param { string } hash - the hash of the stored file
 */
async function getResourceInfo(editor, hash: string) {
    try {
        const response = await fetch(`${config.url.nodeinfo}?identifier=${hash}`, {
            headers: {
                "Content-Type": "application/json",
            },
        });
        const resource = (await response.json()) as Resource;
        const attributes = getAttributes(resource);
        const imageUtils = editor.plugins.get("ImageUtils");
        imageUtils.insertImage(attributes, editor.model.document.selection, "imageInline");
    } catch (error) {
        console.error(error);
    }
    // to close the resource modal
    $("#file-picker").foundation("reveal", "close");
}

/**
 * Handle the returned resource from the resource modal
 *
 * @param { MessageEvent } event - the event
 * @param { Editor } editor - the editor instance
 */
function onResourceReturned(event: MessageEvent, editor) {
    let message;
    try {
        message = JSON.parse(event.data);
    } catch (error: SyntaxError) {
        message = event.data;
    }
    if (message.data != undefined) {
        if (0 in message.data && "hash" in message.data[0]) {
            getResourceInfo(editor, message.data[0].hash as string);
        } else {
            // The file was rejected, probably cause it was the wrong mimetype.
            // Do nothing
        }
    } else {
        // Connecting to your Google Drive
    }
}

export default class InsertResourceCommand extends Command {
    /**
     * display the resource modal and listen for if a resource is returned
     */
    execute() {
        displayResourceModal();
        window.removeEventListener("message", onResourceReturned);
        window.addEventListener("message", (event) => onResourceReturned(event, this.editor));
    }

    /**
     * is called on various events to determine if the command is enabled
     * we use the same method the image plugin uses to determine if the command is enabled
     */
    refresh() {
        this.isEnabled = this.editor.plugins.get("ImageUtils").isImageAllowed();
    }
}
