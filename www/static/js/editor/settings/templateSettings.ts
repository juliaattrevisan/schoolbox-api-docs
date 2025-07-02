import type { TemplateConfig } from "ckeditor5-premium-features";
import type { SbxEditorConfig } from "@js/editor/types/editorTypes";

/**
 * Returns the settings for the template feature in the editor.
 * If there's any templates defined in the config, it will return
 * a TemplateConfig object with the definitions of those templates.
 * @param config - the config to use when creating the editor
 * @returns - the template settings
 */
export function getTemplateSettings(config: SbxEditorConfig): TemplateConfig {
    if (config.templates && config.templates.length > 0) {
        return {
            template: {
                definitions: [
                    ...config.templates.map((template) => ({
                        title: template.title,
                        description: template.description,
                        data: template.body,
                    })),
                ],
            },
        };
    }
    return {};
}
