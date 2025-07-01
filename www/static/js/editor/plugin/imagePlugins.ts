/**
 * Returns the image plugins for the editor
 */

import { ImageInline, ImageBlock, ImageToolbar, ImageStyle, ImageResize, ImageUpload, LinkImage } from "ckeditor5";
import FullWidth from "@js/editor/plugin/fullWidth/FullWidth";

/**
 * All the image plugins
 * @returns - the image plugins
 */
export function getImagePlugins(): (
    | typeof ImageInline
    | typeof ImageBlock
    | typeof ImageToolbar
    | typeof ImageStyle
    | typeof ImageResize
    | typeof ImageUpload
    | typeof LinkImage
    | typeof FullWidth
)[] {
    return [ImageInline, ImageBlock, ImageToolbar, ImageStyle, ImageResize, ImageUpload, LinkImage, FullWidth];
}
