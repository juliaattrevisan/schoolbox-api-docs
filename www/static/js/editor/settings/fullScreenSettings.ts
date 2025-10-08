import type { FullscreenConfig } from "ckeditor5";

/**
 * Returns the settings for the fullscreen button
 * @returns - the fullscreen settings
 */
export function getFullscreenSettings(): { fullscreen: FullscreenConfig } {
    return {
        fullscreen: {
            menuBar: {
                isVisible: false, // hide the top menu bar as it just adds complexity to the existing UI
            },
        },
    };
}
