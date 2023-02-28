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
        source: function (id, filesystem) {
            return `/cms/sendVideo.php?id=${id}&type=${filesystem}`;
        },
        thumbnail: function (id, filesystem) {
            /* Ridiculous quick fix
             */
            filesystem = filesystem.replace("browse", "sbfs").replace("store", "storage");
            return `/cms/videoThumb.php?size=poster&id=${id}&type=${filesystem}`;
        },
        regex: /^\/cms\/sendVideo/,
    },
    /* Audio urls and regex
     */
    audio: {
        source: function (id, filesystem) {
            return `/cms/sendVideo.php?id=${id}&type=${filesystem}`;
        },
        thumbnail: `${window.cdnPrefix}/images/core/component/audio-thumbnail-processing.gif?v=${window.siteVersion}`,
        regex: /^\/cms\/sendVideo/,
    },
    /* Image urls and regex
     * No thumbnails are used for images
     */
    image: {
        source: function (hash) {
            return `/storage/image.php?hash=${hash}`;
        },
        regex: /^\/storage\/image/,
    },

    /* ID of the default folder from which to start browsing resources
     */
    defaultFolderSelector: 0,
};

export default class InsertSchoolboxResourceCommand extends Command {
    execute() {
        $.fn.schoolboxResource({
            allowedFilesystems: "files",
            allowedFiles: "image/*,audio/*,video/*,.heic,.heics,.heif",
            useFolder: false,
            // defaultFolderSelector: defaultFolderSelector,
        });

        $(window)
            .unbind("message")
            .on("message", (event) => {
                let message;

                try {
                    message = JSON.parse(event.originalEvent.data);
                } catch (err) {
                    message = event.originalEvent.data;
                }

                /* Find out how to render this file
                 */
                if (message.data != undefined) {
                    if (0 in message.data && "hash" in message.data[0]) {
                        $.getJSON(config.url.nodeinfo, { identifier: message.data[0].hash }, (responseText) => {
                            if (responseText.type === "image") {
                                const imageUtils = this.editor.plugins.get("ImageUtils");
                                imageUtils.insertImage(
                                    {
                                        src: config.image.source(responseText.hash),
                                        alt: message.data[0].title,
                                    },
                                    this.editor.model.document.selection,
                                    "imageInline"
                                );
                            } else if (responseText.type === "video") {
                                const imageUtils = this.editor.plugins.get("ImageUtils");
                                imageUtils.insertImage(
                                    {
                                        src: config.video.thumbnail(responseText.id, responseText.filesystem),
                                        alt: message.data[0].title,
                                        "data-schoolboxresource-type": responseText.type,
                                        "data-schoolboxresource-filesystem": responseText.filesystem,
                                        "data-schoolboxresource-thumbnail": config.video.thumbnail(
                                            responseText.id,
                                            responseText.filesystem
                                        ),
                                        "data-schoolboxresource-source": config.video.source(
                                            responseText.id,
                                            responseText.filesystem
                                        ),
                                        "data-schoolboxresource-width": 512,
                                        "data-schoolboxresource-height": 288,
                                        "data-schoolboxresource-fullwidth": "true",
                                    },
                                    this.editor.model.document.selection,
                                    "imageInline"
                                );
                            } else if (responseText.type === "audio") {
                                const imageUtils = this.editor.plugins.get("ImageUtils");
                                imageUtils.insertImage(
                                    {
                                        src: config.audio.thumbnail,
                                        alt: message.data[0].title,
                                        "data-schoolboxresource-type": responseText.type,
                                        "data-schoolboxresource-filesystem": responseText.filesystem,
                                        "data-schoolboxresource-thumbnail": config.audio.thumbnail,
                                        "data-schoolboxresource-source": config.audio.source(
                                            responseText.id,
                                            responseText.filesystem
                                        ),
                                        "data-schoolboxresource-width": 64,
                                        "data-schoolboxresource-height": 64,
                                        "data-schoolboxresource-fullwidth": "true",
                                    },
                                    this.editor.model.document.selection,
                                    "imageInline"
                                );
                            }
                            /* Shut file picker modal, go back to Dialog
                             */
                            $("#file-picker").foundation("reveal", "close");
                        });
                    } else {
                        // The file was rejected, probably cause it was the wrong mimetype.
                        // Do nothing
                    }
                } else {
                    /* Connecting to your Google Drive
                     */
                }
            });
    }

    refresh() {
        const model = this.editor.model;
        const selection = model.document.selection;
        const allowedIn = model.schema.findAllowedParent(selection.getFirstPosition(), "simpleBox");

        this.isEnabled = allowedIn !== null;
    }
}
