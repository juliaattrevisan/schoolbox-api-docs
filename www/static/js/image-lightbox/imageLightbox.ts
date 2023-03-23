/**
 * To have only one LightGallery instance on a page, we need to store it globally
 * Refer to www/static/js/image-lightbox/imageLightboxUtils.ts::checkForLightGallery for how
 * this is used.
 */

import lightGallery from "lightgallery";
import "lightgallery/css/lightgallery-bundle.min.css";
import "./imageLightbox.css";

window.sbProps.lightGallery = lightGallery;

document.dispatchEvent(new CustomEvent("IMAGE-LIGHTBOX__LOADED"));
