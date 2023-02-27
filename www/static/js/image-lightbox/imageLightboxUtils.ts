import { isScriptAdded, injectScript } from "@js/util/domUtils";

const imageLightBoxSrc = `${window.cdnPrefix}/js/bundle/imageLightbox.js?v=${window.siteVersion}`;

/**
 * Check if lightGallery is already loaded, if not, load it.
 *
 * @param { Function } initLightGallery - the function to call once lightGallery is loaded
 */
export function checkForLightGallery(initLightGallery: Function) {
    if (window.sbProps.lightGallery) {
        // it's already loaded and available on the window object
        initLightGallery();
    } else if (!isScriptAdded(imageLightBoxSrc)) {
        // it hasn't been added to the page yet
        injectScript(imageLightBoxSrc) as Promise<void>;
        document.addEventListener("IMAGE-LIGHTBOX__LOADED", initLightGallery);
    } else {
        // otherwise, wait for the script to load
        document.addEventListener("IMAGE-LIGHTBOX__LOADED", initLightGallery);
    }
}
