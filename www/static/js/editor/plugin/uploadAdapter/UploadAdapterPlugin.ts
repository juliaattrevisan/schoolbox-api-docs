/**
 * To initates our UploadAdapter plugin to display resources
 * uploaded to the server
 */

import UploadAdapter from "@js/editor/plugin/uploadAdapter/UploadAdapter";

/**
 * Add our custom upload adapter
 *
 * @param { CKEditor } editor - the CKEditor instance
 */
export default function UploadAdapterPlugin(editor) {
    editor.plugins.get("FileRepository").createUploadAdapter = (loader) => {
        return new UploadAdapter(loader, editor);
    };
}
