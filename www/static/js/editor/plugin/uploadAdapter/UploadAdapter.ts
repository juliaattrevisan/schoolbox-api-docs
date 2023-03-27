/**
 * Our custom CKEditor 5 image uploader, heavily referenced from
 * https://ckeditor.com/docs/ckeditor5/latest/framework/guides/deep-dive/upload-adapter.html
 *
 */

import { getURL } from "@js/editor/plugin/resource/resourceUtils";
import type { EditorConfig } from "@js/editor/types/editorTypes";

export default class UploadAdapter {
    loader: unknown;

    xhr: XMLHttpRequest;

    editor: object;

    /**
     * Create a new instance of MyUploadAdapter
     *
     * @param { unknown } loader - see https://ckeditor.com/docs/ckeditor5/latest/api/module_upload_filerepository-FileLoader.html
     * @param { CKEditor } editor - the CKEditor instance
     */
    constructor(loader, editor) {
        this.loader = loader;
        this.editor = editor;
        this.xhr = new XMLHttpRequest();
    }

    /**
     * Initializes the XMLHttpRequest object using the URL passed to the constructor.
     */
    initRequest() {
        this.xhr.open("POST", "/storage/asyncUpload.php", true);
        this.xhr.responseType = "json";
    }

    /**
     * Upload progress when it is supported. The file loader has the #uploadTotal and #uploaded
     * properties which are used e.g. to display the upload progress bar in the editor user interface.
     *
     * @param { ProgressEvent } event - to display load progress
     */
    onLoading(event: ProgressEvent) {
        if (event.lengthComputable) {
            this.loader.uploadTotal = event.total;
            this.loader.uploaded = event.loaded;
        }
    }

    /**
     * Once the image is successfully uploaded
     * Learn more in the UploadAdapter#upload documentation.
     * If the upload is successful, resolve the upload promise with an object containing
     * at least the "default" URL, pointing to the image on the server.
     * This URL will be used to display the image in the content. Learn more in the
     * UploadAdapter#upload documentation.
     *
     * @param { Function } resolve - when the file uploads successfully
     * @param { Function } reject - when the file fails
     * @param { string } genericErrorText - message to display when failed if a message isn't passed through
     * @returns { Function | void } - return the reject if the the load has failed
     */
    onLoaded(resolve: Function, reject: Function, genericErrorText: string) {
        const response = this.xhr.response as JSON;
        console.warn(response);
        if (!response || !response.success) {
            return reject(response && response.error ? response.message : genericErrorText);
        }
        const meta = response.meta as JSON;
        const config = this.editor.config.sbConfig as EditorConfig;
        const imageUtils = this.editor.plugins.get("ImageUtils");
        if (meta.file.type === "image") {
            const attributes = {
                src: getURL(meta.file._links.image, config),
                alt: meta.name,
            };
            this.editor.model.change((writer) => {
                const image = imageUtils.insertImage(attributes, this.editor.model.document.selection, "imageInline");
                writer.setSelection(image.parent, "end");
                this.editor.execute("enter");
            });
        }
        if (meta.file.type === "document") {
            const attributes = {
                src: getURL(meta.file._links.icon, config),
                alt: meta.name,
                linkHref: getURL(meta.file._links.fetch, config),
                linkToggleDownloadable: "true",
            };
            imageUtils.insertImage(attributes, this.editor.model.document.selection, "imageInline");
            this.editor.model.change((writer) => {
                const image = imageUtils.insertImage(attributes, this.editor.model.document.selection, "imageInline");
                this.editor.execute("resizeImage", { width: "24px" });
                writer.setSelection(image.parent, "end");
                this.editor.model.insertContent(
                    writer.createText(`${meta.name}`, {
                        linkHref: getURL(meta.file._links.fetch, config),
                        linkToggleDownloadable: "true",
                    })
                );
                this.editor.execute("enter");
            });
        }
    }

    /**
     * Initializes XMLHttpRequest listeners.
     *
     * @param { Function } resolve - when the file uploads successfully
     * @param { Function } reject - when the file fails
     * @param { File } file - the file being uploaded
     */
    initListeners(resolve: Function, reject: Function, file: File) {
        const genericErrorText = `Couldn't upload file: ${file.name}.`;
        this.xhr.addEventListener("error", () => reject(genericErrorText));
        this.xhr.addEventListener("abort", () => reject());
        if (this.xhr.upload) {
            this.xhr.upload.addEventListener("progress", (event: ProgressEvent) => this.onLoading(event));
        }
        this.xhr.addEventListener("load", () => this.onLoaded(resolve, reject, genericErrorText));
    }

    /**
     * Prepares the data and sends the request.
     * @todo - Important note: This is the right place to implement security mechanisms
     * like authentication and CSRF protection. For instance, you can use
     * XMLHttpRequest.setRequestHeader() to set the request headers containing
     * the CSRF token generated earlier by your application.
     *
     * @param { File } file - the file to upload
     */
    sendRequest(file: File) {
        const data = new FormData();
        data.append("upload", file);
        this.xhr.send(data);
    }

    /**
     * Starts the upload process.
     *
     * @returns { Promise } - new Promise request
     */
    upload() {
        return this.loader.file.then(
            (file: File) =>
                new Promise((resolve, reject) => {
                    this.initRequest();
                    this.initListeners(resolve, reject, file);
                    this.sendRequest(file);
                })
        );
    }

    /**
     * Aborts the upload process.
     */
    abort() {
        if (this.xhr) {
            this.xhr.abort();
        }
    }
}
