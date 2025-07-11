/**
 * Returns the default config setup for a CKEditor 5 instance
 * for use in the Editor.create() method
 */

import { type EditorConfig } from "ckeditor5";
import { getPlugins } from "@js/editor/plugin/editorPlugins";
import { getSettings } from "@js/editor/settings/editorSettings";
import { getToolbar } from "@js/editor/toolbar/editorToolbar";
import { type SbxEditorConfig } from "@js/editor/types/editorTypes";

/**
 * Return the default configuration
 * @param config - the default config
 * @returns - refer to https://ckeditor.com/docs/ckeditor5/latest/api/module_core_editor_editorconfig-EditorConfig.html
 */
export function getConfig(config: SbxEditorConfig): EditorConfig & { sbConfig: SbxEditorConfig } {
    return {
        ...getPlugins(config),
        licenseKey: window.sbProps.ckeditor5LicenseKey,
        toolbar: getToolbar(config),
        ...getSettings(config),
        sbConfig: { ...config }, // to be able to access our editor config from within the editor,
    };
}
