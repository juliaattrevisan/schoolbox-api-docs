import { isScriptAdded, injectScript } from "@js/util/domUtils";

function initLightGallery() {
    window.sbProps.lightGalleryInit();
    const newStyle = document.createElement("style");
    newStyle.appendChild(
        document.createTextNode(`
        @font-face {
            font-family: "lg";
            src: url("${window.cdnPrefix}/fonts/lg.woff2?io9a6k") format("woff2"), url("${window.cdnPrefix}/fonts/lg.ttf?io9a6k") format("truetype"),
                url("${window.cdnPrefix}/fonts/lg.woff?io9a6k") format("woff"), url("${window.cdnPrefix}/fonts/lg.svg?io9a6k#lg") format("svg");
        }
        .lg-outer .lg-item:not(.lg-start-end-progress) {
            background: url("${window.cdnPrefix}/images/loading.gif") no-repeat scroll center center transparent;
        }
        `)
    );
    document.head.appendChild(newStyle);
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
