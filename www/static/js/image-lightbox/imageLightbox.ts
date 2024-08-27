/**
 * To have only one LightGallery instance on a page, we need to store it globally
 * Refer to www/static/js/image-lightbox/imageLightboxUtils.ts::checkForLightGallery for how
 * this is used.
 */

import lightGallery from "lightgallery";
import type { LightGallery } from "lightgallery/lightgallery";
import type { LightGallerySettings } from "lightgallery/lg-settings";
import type { GalleryItem } from "lightgallery/lg-utils";
import type { VideoSource } from "lightgallery/plugins/video/types";
import "lightgallery/css/lightgallery-bundle.min.css";
import imageLightboxDefaults from "./imageLightboxDefaults";
import "./imageLightbox.css";

/**
 * Initialize lightGallery using a elements with rel="lightbox" as the source
 * Each image will be grouped together based on the rel attribute
 * The rel attribute should be in the format of "lightbox[<group name>]"
 */
function initLightGallery(): void {
    // Group the images by their rel attribute
    const lightboxGroups: { [key: string]: HTMLElement[] } = {};
    const lightboxImages: NodeListOf<HTMLElement> = document.querySelectorAll("a[rel^='lightbox']");
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
        const existingLightGallery = window.sbProps.lightGalleries[key];
        if (existingLightGallery !== undefined) {
            existingLightGallery.destroy();
        }
        lightboxElement.setAttribute("data-lightbox-loaded", "true");

        // Initialize lightGallery on the new element
        const lightboxSettings: LightGallerySettings = {
            dynamic: true,
            dynamicEl: lightboxGroups[key].map((image): GalleryItem => {
                return {
                    src: image.getAttribute("href") || image.getAttribute("data-src") || undefined,
                    thumb: image.querySelector("img")?.getAttribute("src") || image.getAttribute("href") || undefined,
                    subHtml: image.getAttribute("title") || undefined,
                    video: (image.getAttribute("data-video") as unknown as VideoSource | null) || undefined,
                    poster: image.getAttribute("data-poster") || undefined,
                };
            }),
        };

        window.sbProps.lightGalleries[key] = lightGallery(
            lightboxElement,
            Object.assign(imageLightboxDefaults, lightboxSettings),
        );

        // For each image, add a click event to open the lightbox
        lightboxGroups[key].forEach((image, index) => {
            const selectedGallery = window.sbProps.lightGalleries[key];
            if (selectedGallery === undefined) return; // no instance found

            const selectedIndex = index;
            image.addEventListener("click", (e: MouseEvent) => {
                e.preventDefault();
                selectedGallery.openGallery(selectedIndex);
            });
        });
    });
}

interface ImageLightboxCreateEvent extends CustomEvent {
    detail: {
        targetKey: string;
        options: LightGallerySettings;
        onCreated?: (gallery: LightGallery) => void;
    };
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
    const eventDetail = (e as ImageLightboxCreateEvent).detail;
    let targetElement = document.querySelector(`[data-image-lightbox='${eventDetail.targetKey}']`) as HTMLElement;

    // Create a new element for the lightbox, hidden on the page
    // this is to ensure that we have a usable element to pass to lightGallery
    if (targetElement === null) {
        targetElement = document.createElement("div");
        targetElement.setAttribute("data-image-lightbox", eventDetail.targetKey);
        targetElement.style.display = "none";
        document.body.appendChild(targetElement);
    }

    // Overwrite an existing lightbox to avoid creating duplicate lightboxes
    const existingLightGallery = window.sbProps.lightGalleries[eventDetail.targetKey];
    if (existingLightGallery !== undefined) {
        existingLightGallery.destroy();
    }

    targetElement.setAttribute("data-lightbox-loaded", "true");
    const newLightGallery = lightGallery(targetElement, Object.assign(imageLightboxDefaults, eventDetail.options));

    window.sbProps.lightGalleries[eventDetail.targetKey] = newLightGallery;

    document.dispatchEvent(
        new CustomEvent("IMAGE-LIGHTBOX__CREATED", { detail: { targetKey: eventDetail.targetKey } }),
    );
    // If the caller wants to do something after the lightbox is created, run the callback
    if (eventDetail.onCreated) {
        eventDetail.onCreated(newLightGallery);
    }
});

export type InitLightGallery = typeof initLightGallery;

window.sbProps.lightGallery = lightGallery;
window.sbProps.lightGalleryInit = initLightGallery; // Allow other scripts to call this function to reinit lightGallery
window.sbProps.lightGalleries = {};

document.dispatchEvent(new CustomEvent("IMAGE-LIGHTBOX__LOADED"));
