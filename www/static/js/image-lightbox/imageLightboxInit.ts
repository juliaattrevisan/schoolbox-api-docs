import { isScriptAdded, injectScript } from "@js/util/domUtils";

function initLightGallery() {
    window.sbProps.lightGalleryInit();
}

/**
 * Check if lightGallery is already loaded, if not, load it.
*
* @param { Function } initLightGallery - the function to call once lightGallery is loaded
*/
function checkForLightGallery(initLightGallery: Function) {
    const imageLightBoxSrc = `${window.cdnPrefix}/js/bundle/imageLightbox.js?v=${window.siteVersion}`;
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

// We need to make sure window.sbProps is available before we can load the lightbox plugin
if (window.sbProps?.imageLightboxLicenseKey) {
    checkForLightGallery(initLightGallery);
} else {
    document.addEventListener("DOMContentLoaded", () => {
        checkForLightGallery(initLightGallery);
    });
}
