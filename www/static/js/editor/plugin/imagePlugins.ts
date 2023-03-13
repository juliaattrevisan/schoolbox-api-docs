/**
 * Returns the image plugins for the editor
 *
 * @todo - there's currently an issue with preserving extra attributes on the imageBlock element
 * as imageInline appears to work fine, all images are currently set to imageInline, so all imageBlock
 * related functionality is currently disabled
 */

import Image from "@ckeditor/ckeditor5-image/src/image";
import ImageToolbar from "@ckeditor/ckeditor5-image/src/imagetoolbar";
// import ImageCaption from "@ckeditor/ckeditor5-image/src/imagecaption";
import ImageStyle from "@ckeditor/ckeditor5-image/src/imagestyle";
import ImageResize from "@ckeditor/ckeditor5-image/src/imageresize";
// import LinkImage from "@ckeditor/ckeditor5-link/src/linkimage";
import LinkImage from "@js/editor/plugin/link/LinkImage";
import ImageInsert from "@ckeditor/ckeditor5-image/src/imageinsert";
import FullWidth from "@js/editor/plugin/fullWidth/FullWidth";

/**
 * All the image plugins
 *
 * @returns { object } - the image plugins
 */
export function getImagePlugins() {
    // return [Image, ImageToolbar, ImageCaption, ImageStyle, ImageResize, LinkImage, ImageInsert, FullWidth];
    return [Image, ImageToolbar, ImageStyle, ImageResize, LinkImage, ImageInsert, FullWidth];
}
