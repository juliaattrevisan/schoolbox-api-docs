import lgAutoplay from "lightgallery/plugins/autoplay";
import { checkForLightGallery } from "@js/image-lightbox/imageLightboxUtils";

const imageLightboxDefaults = {
    plugins: [lgAutoplay],
    speed: 0,
    zoom: true,
    zoomSize: "contain",
    actualSize: false,
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
