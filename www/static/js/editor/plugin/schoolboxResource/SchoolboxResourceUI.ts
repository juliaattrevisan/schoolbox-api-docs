/**
 * The Schoolbox Resource button
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import ButtonView from "@ckeditor/ckeditor5-ui/src/button/buttonview";

/**
 * Returns the button to display in the toolbar
 *
 * @returns { ButtonView } - the button to display in the toolbar
 */
function getButton() {
    const starIcon =
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M11.627 16.5zm5.873-.196zm0-7.001V8h-13v8.5h4.341c.191.54.457 1.044.785 1.5H2a1.5 1.5 0 0 1-1.5-1.5v-13A1.5 1.5 0 0 1 2 2h4.5a1.5 1.5 0 0 1 1.06.44L9.122 4H16a1.5 1.5 0 0 1 1.5 1.5v1A1.5 1.5 0 0 1 19 8v2.531a6.027 6.027 0 0 0-1.5-1.228zM16 6.5v-1H8.5l-2-2H2v13h1V8a1.5 1.5 0 0 1 1.5-1.5H16z"></path><path d="M14.5 19.5a5 5 0 1 1 0-10 5 5 0 0 1 0 10zM15 14v-2h-1v2h-2v1h2v2h1v-2h2v-1h-2z"></path></svg>';
    const button = new ButtonView();
    button.label = "Embed Resource";
    button.tooltip = true;
    button.withText = false;
    button.icon = starIcon;
    return button;
}

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
            return "/cms/sendVideo.php?id=" + id + "&type=" + filesystem;
        },
        thumbnail: function (id, filesystem) {
            /* Ridiculous quick fix
             */
            filesystem = filesystem.replace("browse", "sbfs").replace("store", "storage");

            return "/cms/videoThumb.php?size=poster&id=" + id + "&type=" + filesystem;
        },
        regex: /^\/cms\/sendVideo/,
    },

    /* Audio urls and regex
     */
    audio: {
        source: function (id, filesystem) {
            return "/cms/sendVideo.php?id=" + id + "&type=" + filesystem;
        },
        thumbnail: window.cdnPrefix + "/images/core/component/audio-thumbnail-processing.gif?v=" + window.siteVersion,
        regex: /^\/cms\/sendVideo/,
    },

    /* Image urls and regex
     * No thumbnails are used for images
     */
    image: {
        source: function (hash) {
            return "/storage/image.php?hash=" + hash;
        },
        regex: /^\/storage\/image/,
    },

    /* ID of the default folder from which to start browsing resources
     */
    defaultFolderSelector: 0,
};

export default class SchoolboxResourceUI extends Plugin {
    /**
     * Register the button in the editor's UI component factory.
     */
    init() {
        this.editorId = this.editor.id;
        this.editor.ui.componentFactory.add("schoolboxResource", () => {
            const button = getButton();

            button.on("execute", () => {
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
                                                src: `/storage/image.php?hash=${responseText.hash}`,
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
                                                title: message.data[0].title,
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
                                                title: message.data[0].title,
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
            });

            return button;
        });
    }
}
