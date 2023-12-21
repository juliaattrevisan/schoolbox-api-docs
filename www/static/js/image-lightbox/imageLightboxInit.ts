import { isScriptAdded, injectScript } from "@js/util/domUtils";

/**
 * Overwrite the font and loading images used by lightGallery to ensure we use the CDN,
 * otherwise they will be loaded from the current domain
 */
function overwriteStaticAssets() {
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

function initLightGallery() {
    window.sbProps.lightGalleryInit();
    overwriteStaticAssets();
}

/**
 * Check if lightGallery is already loaded, if not, load it.
 * @param { Function } initLightGallery - the function to call once lightGallery is loaded
 */
function checkForLightGallery(initLightGallery: Function) {
    // We need to ensure that the license key is available before loading the plugin
    // If it's not, wait for the DOM to load and try again
    if (!window.sbProps?.imageLightboxLicenseKey) {
        document.addEventListener("DOMContentLoaded", () => {
            checkForLightGallery(initLightGallery);
        });
        return;
    }

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

checkForLightGallery(initLightGallery);
