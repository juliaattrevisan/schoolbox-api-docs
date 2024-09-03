/**
 * Our custom CKEditor 5 image uploader, heavily referenced from
 * https://ckeditor.com/docs/ckeditor5/latest/framework/guides/deep-dive/upload-adapter.html
 * It calls asyncUpload.php and then returns the resource object
 */

import type { AsyncUploadResponse, Resource } from "@js/editor/plugin/resource/resourceUtils";
import type { Editor } from "@ckeditor/ckeditor5-core";
import type { FileLoader, UploadResponse } from "@ckeditor/ckeditor5-upload/src/filerepository";

type UploadResolve = (value: UploadResponse | PromiseLike<UploadResponse>) => void;
type UploadReject = (reason?: string) => void;

export default class UploadAdapter {
    loader: FileLoader;

    xhr: XMLHttpRequest;

    editor: Editor;

    /**
     * Create a new instance of MyUploadAdapter
     * @param loader - see https://ckeditor.com/docs/ckeditor5/latest/api/module_upload_filerepository-FileLoader.html
     * @param editor - the CKEditor instance
     */
    constructor(loader: FileLoader, editor: Editor) {
        this.loader = loader;
        this.editor = editor;
        this.xhr = new XMLHttpRequest();
    }

    /**
     * Initializes the XMLHttpRequest object using the URL passed to the constructor.
     */
    initRequest(): void {
        this.xhr.open("POST", "/storage/asyncUpload.php", true);
        this.xhr.responseType = "json";
    }

    /**
     * Upload progress when it is supported. The file loader has the #uploadTotal and #uploaded
     * properties which are used e.g. to display the upload progress bar in the editor user interface.
     * @param event - to display load progress
     */
    onLoading(event: ProgressEvent): void {
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
     * @param resolve - when the file uploads successfully
     * @param reject - when the file fails
     * @param genericErrorText - message to display when failed if a message isn't passed through
     * @returns - return the reject if the the load has failed
     */
    onLoaded(resolve: UploadResolve, reject: UploadReject, genericErrorText: string): void | UploadReject {
        const response = this.xhr.response as AsyncUploadResponse;
        if (!response || !response.success) {
            return reject(response && response.error ? response.message : genericErrorText);
        }
        const resource: Resource = {
            filesystem: "store",
            hash: response.meta.file.hash,
            mime: response.meta.file.mime,
            name: response.meta.name,
            type: response.meta.file.type,
            default: response.meta.file._links.fetch,
        };
        resolve(resource);

        return undefined;
    }

    /**
     * Initializes XMLHttpRequest listeners.
     * @param resolve - when the file uploads successfully
     * @param reject - when the file fails
     * @param file - the file being uploaded
     */
    initListeners(resolve: UploadResolve, reject: UploadReject, file: File): void {
        const genericErrorText = `Couldn't upload file: ${file.name}.`;
        this.xhr.addEventListener("error", () => reject(genericErrorText));
        this.xhr.addEventListener("abort", () => reject("aborted"));
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
     * @param file - the file to upload
     */
    sendRequest(file: File): void {
        const data = new FormData();
        data.append("upload", file);
        this.xhr.send(data);
    }

    /**
     * Starts the upload process.
     * @returns - new Promise request
     */
    async upload(): Promise<UploadResponse> {
        return this.loader.file.then((file: File | null) => {
            if (!file) {
                throw new Error("No file to upload");
            }

            return new Promise((resolve, reject) => {
                this.initRequest();
                this.initListeners(resolve, reject, file);
                this.sendRequest(file);
            });
        });
    }

    /**
     * Aborts the upload process.
     */
    abort(): void {
        if (this.xhr) {
            this.xhr.abort();
        }
    }
}
