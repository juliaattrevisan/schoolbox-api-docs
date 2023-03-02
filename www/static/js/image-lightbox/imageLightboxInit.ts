import lgAutoplay from "lightgallery/plugins/autoplay";
import lgFullScreen from "lightgallery/plugins/fullscreen";
import lgVideo from "lightgallery/plugins/video";

import { checkForLightGallery } from "@js/image-lightbox/imageLightboxUtils";

const imageLightboxDefaults = {
    plugins: [lgAutoplay, lgFullScreen, lgVideo],
    licenseKey: "B3EFE861-7F3F-43AA-9B2C-98540F09D470",
    speed: 0,
};

/**
 * Initialize lightGallery on all elements with the data-image-lightbox attribute.
 */
function initLightGallery() {
    const lightboxElements = document.querySelectorAll("[data-image-lightbox]");
    lightboxElements.forEach((element) => {
        const targetElement = element;
        // Avoid duplicate lightboxes on the same element
        if (targetElement.getAttribute("data-lightbox-loaded") === "true") {
            return;
        }
        targetElement.setAttribute("data-lightbox-loaded", "true");
        window.sbProps.lightGallery(targetElement, imageLightboxDefaults);
    });
}

checkForLightGallery(initLightGallery);
