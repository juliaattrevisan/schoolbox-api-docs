/**
 * To have only one LightGallery instance on a page, we need to store it globally
 * Refer to www/static/js/image-lightbox/imageLightboxUtils.ts::checkForLightGallery for how
 * this is used.
 */

import lightGallery from "lightgallery";
import imageLightboxDefaults from "./imageLightboxDefaults";
import "lightgallery/css/lightgallery-bundle.min.css";
import "./imageLightbox.css";

window.sbProps.lightGallery = lightGallery;
window.sbProps.lightGalleryInit = initLightGallery; // Allow other scripts to call this function to reinit lightGallery
window.sbProps.lightGalleries = {};

/**
 * Initialize lightGallery using a elements with rel="lightbox" as the source
 * Each image will be grouped together based on the rel attribute
 * The rel attribute should be in the format of "lightbox[<group name>]"
 */
function initLightGallery(): void {
    // Group the images by their rel attribute
    let lightboxGroups: { [key: string]: HTMLElement[] } = {};
    let lightboxImages = document.querySelectorAll("a[rel^='lightbox']") as NodeListOf<HTMLElement>;
    lightboxImages.forEach((image) => {
        const rel = image.getAttribute("rel");
        if (rel !== null) {
            if (!lightboxGroups[rel]) {
                lightboxGroups[rel] = [];
            }
            lightboxGroups[rel].push(image);
        }
    });

    Object.keys(lightboxGroups).forEach((key) => {
        // Find a matching data-image-lightbox element for the key
        let lightboxElement = document.querySelector(`[data-image-lightbox='${key}']`) as HTMLElement;
        if (lightboxElement === null) {
            // Create a new element for the lightbox, hidden on the page
            lightboxElement = document.createElement("div");
            lightboxElement.setAttribute("data-image-lightbox", key);
            lightboxElement.style.display = "none";
            document.body.appendChild(lightboxElement);
        }

        // Destroy the existing lightbox to avoid creating duplicate lightboxes
        if (window.sbProps.lightGalleries[key]) {
            window.sbProps.lightGalleries[key].destroy(true);
        }
        lightboxElement.setAttribute("data-lightbox-loaded", "true");

        // Initialize lightGallery on the new element
        const lightboxSettings = {
            dynamic: true,
            dynamicEl: lightboxGroups[key].map((image) => {
                return {
                    src: image.getAttribute("href") || image.getAttribute("data-src"),
                    thumb: image.querySelector("img")?.getAttribute("src") || image.getAttribute("href"),
                    subHtml: image.getAttribute("title"),
                    video: image.getAttribute("data-video"),
                    poster: image.getAttribute("data-poster"),
                };
            }),
        };
        window.sbProps.lightGalleries[key] = window.sbProps.lightGallery(
            lightboxElement,
            Object.assign(imageLightboxDefaults, lightboxSettings)
        );

        // For each image, add a click event to open the lightbox
        lightboxGroups[key].forEach((image, index) => {
            const selectedGallery = window.sbProps.lightGalleries[key];
            const selectedIndex = index;
            image.addEventListener("click", (e: MouseEvent) => {
                e.preventDefault();
                selectedGallery.openGallery(selectedIndex);
            });
        });
    });
}

/**
 * Allow pages to create a lightbox dynamically using the IMAGE-LIGHTBOX__CREATE event
 * This is useful for pages which need create a new lightbox after the page has already loaded
 * For example, preview.js needs to create a lightbox
 *
 * The event detail should contain the following:
 * - targetKey: the rel key to distinguish the lightbox
 * - options: options to pass to lightGallery
 */
document.addEventListener("IMAGE-LIGHTBOX__CREATE", (e) => {
    let targetElement = document.querySelector("[data-image-lightbox='" + e.detail.targetKey + "']") as HTMLElement;

    // Create a new element for the lightbox, hidden on the page
    // this is to ensure that we have a usable element to pass to lightGallery
    if (targetElement === null) {
        targetElement = document.createElement("div");
        targetElement.setAttribute("data-image-lightbox", e.detail.targetKey);
        targetElement.style.display = "none";
        document.body.appendChild(targetElement);
    }

    // Overwrite an existing lightbox to avoid creating duplicate lightboxes
    if (window.sbProps.lightGalleries[e.detail.targetKey]) {
        window.sbProps.lightGalleries[e.detail.targetKey].destroy(true);
    }

    targetElement.setAttribute("data-lightbox-loaded", "true");
    window.sbProps.lightGalleries[e.detail.targetKey] = window.sbProps.lightGallery(
        targetElement,
        Object.assign(imageLightboxDefaults, e.detail.options)
    );

    document.dispatchEvent(new CustomEvent("IMAGE-LIGHTBOX__CREATED", { detail: { targetKey: e.detail.targetKey } }));
    // If the caller wants to do something after the lightbox is created, run the callback
    if (e.detail.onCreated) {
        e.detail.onCreated(window.sbProps.lightGalleries[e.detail.targetKey]);
    }
});

document.dispatchEvent(new CustomEvent("IMAGE-LIGHTBOX__LOADED"));
