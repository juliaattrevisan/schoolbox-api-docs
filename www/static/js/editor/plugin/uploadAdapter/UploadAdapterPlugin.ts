/**
 * To initiates our UploadAdapter plugin to display resources
 * uploaded to the server
 */

import type { Editor } from "@ckeditor/ckeditor5-core";
import UploadAdapter from "@js/editor/plugin/uploadAdapter/UploadAdapter";

/**
 * Add our custom upload adapter
 * @param editor - the CKEditor instance
 */
export default function UploadAdapterPlugin(editor: Editor): void {
    editor.plugins.get("FileRepository").createUploadAdapter = (loader): UploadAdapter => {
        return new UploadAdapter(loader, editor);
    };
}
