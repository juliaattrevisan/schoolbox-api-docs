/**
 * Our custom image uploader, heavily referenced from https://ckeditor.com/docs/ckeditor5/latest/framework/guides/deep-dive/upload-adapter.html
 *
 */
export default class MyUploadAdapter {
    /**
     * Create a new instance of MyUploadAdapter
     *
     * @param { FileLoader } loader - see https://ckeditor.com/docs/ckeditor5/latest/api/module_upload_filerepository-FileLoader.html
     */
    constructor(loader) {
        this.loader = loader;
    }

    /**
     * Initializes the XMLHttpRequest object using the URL passed to the constructor.
     */
    initRequest() {
        const xhr = (this.xhr = new XMLHttpRequest());

        // Note that your request may look different. It is up to you and your editor
        // integration to choose the right communication channel. This example uses
        // a POST request with JSON as a data structure but your configuration
        // could be different.
        xhr.open("POST", "/storage/asyncUpload.php", true);
        xhr.responseType = "json";
    }

    /**
     * Upload progress when it is supported. The file loader has the #uploadTotal and #uploaded
     * properties which are used e.g. to display the upload progress bar in the editor user interface.
     *
     * @param { ProgressEvent } event - to display load progress
     * @param { FileLoader } loader - the file loader
     */
    onLoading(event: ProgressEvent, loader) {
        if (event.lengthComputable) {
            loader.uploadTotal = event.total;
            loader.uploaded = event.loaded;
        }
    }

    /**
     * Once the image is successfully uploaded
     *
     * @param { Function } resolve - when the file uploads successfully
     * @param { Function } reject - when the file fails
     * @param { XMLHttpRequest } xhr - the XMLHttpRequest request
     */
    onLoaded(resolve, reject, xhr) {
        const response = xhr.response;

        // This example assumes the XHR server's "response" object will come with
        // an "error" which has its own "message" that can be passed to reject()
        // in the upload promise.
        //
        // Your integration may handle upload errors in a different way so make sure
        // it is done properly. The reject() function must be called when the upload fails.
        // if (!response || response.error) {
        // return reject(response && response.error ? response.error.message : genericErrorText);
        // }

        // If the upload is successful, resolve the upload promise with an object containing
        // at least the "default" URL, pointing to the image on the server.
        // This URL will be used to display the image in the content. Learn more in the
        // UploadAdapter#upload documentation.
        resolve({
            default: response.meta.file._links.image,
        });
    }

    /**
     * Initializes XMLHttpRequest listeners.
     *
     * @param { Function } resolve - when the file uploads successfully
     * @param { Function } reject - when the file fails
     * @param { File } file - the file being uploaded
     */
    initListeners(resolve, reject, file) {
        const xhr = this.xhr as XMLHttpRequest;
        const loader = this.loader;
        const genericErrorText = `Couldn't upload file: ${file.name}.`;

        xhr.addEventListener("error", () => reject(genericErrorText));
        xhr.addEventListener("abort", () => reject());
        if (xhr.upload) {
            xhr.upload.addEventListener("progress", (event) => this.onLoading(event, loader));
        }
        xhr.addEventListener("load", () => this.onLoaded(resolve, reject, xhr));
    }

    /**
     * Prepares the data and sends the request.
     *
     * @param { File } file - the file to upload
     */
    sendRequest(file: File) {
        // Prepare the form data.
        const data = new FormData();

        data.append("upload", file);

        // Important note: This is the right place to implement security mechanisms
        // like authentication and CSRF protection. For instance, you can use
        // XMLHttpRequest.setRequestHeader() to set the request headers containing
        // the CSRF token generated earlier by your application.

        // Send the request.
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
