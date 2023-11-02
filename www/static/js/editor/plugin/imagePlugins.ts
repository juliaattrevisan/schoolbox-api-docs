/**
 * Returns the image plugins for the editor
 * @todo - there's currently an issue with preserving extra attributes on the imageBlock element
 * as imageInline appears to work fine, all images are currently set to imageInline, so all imageBlock
 * related functionality is currently disabled
 */

import { ImageInline, ImageToolbar, ImageStyle, ImageResize } from "@ckeditor/ckeditor5-image";
// import { ImageCaption } from "@ckeditor/ckeditor5-image";
import LinkImage from "@js/editor/plugin/link/LinkImage";
import FullWidth from "@js/editor/plugin/fullWidth/FullWidth";

/**
 * All the image plugins
 * @returns { object } - the image plugins
 */
export function getImagePlugins() {
    // return [Image, ImageToolbar, ImageCaption, ImageStyle, ImageResize, LinkImage, ImageInsert, FullWidth];
    return [ImageInline, ImageToolbar, ImageStyle, ImageResize, LinkImage, FullWidth];
}
