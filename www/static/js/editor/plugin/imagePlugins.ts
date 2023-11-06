/**
 * Returns the image plugins for the editor
 */

import { ImageInline, ImageToolbar, ImageStyle, ImageResize } from "@ckeditor/ckeditor5-image";
// import LinkImage from "@js/editor/plugin/link/LinkImage";
import FullWidth from "@js/editor/plugin/fullWidth/FullWidth";

/**
 * All the image plugins
 * @returns { object } - the image plugins
 */
export function getImagePlugins() {
    // return [ImageInline, ImageToolbar, ImageStyle, ImageResize, LinkImage, FullWidth];
    return [ImageInline, ImageToolbar, ImageStyle, ImageResize, FullWidth];
}
