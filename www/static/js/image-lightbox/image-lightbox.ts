import lightGallery from "lightgallery";
import lgAutoplay from "lightgallery/plugins/autoplay";
import "lightgallery/css/lightgallery-bundle.min.css";

import "./image-lightbox.css";

const imageLightboxDefaults = {
    plugins: [lgAutoplay],
    speed: 0
};

const lightboxElements = document.querySelectorAll('[data-image-lightbox]');
lightboxElements.forEach((element) => {
    const targetElement = element;

    // Avoid duplicate lightboxes on the same element
    if (targetElement.getAttribute("data-lightbox-loaded") === "true") {
        return;
    }
    targetElement.setAttribute("data-lightbox-loaded", "true");

    const lightbox = lightGallery(targetElement, imageLightboxDefaults);
}
