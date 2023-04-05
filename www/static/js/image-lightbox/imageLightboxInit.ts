import imageLightboxDefaults from "./imageLightboxDefaults";
import { checkForLightGallery } from "@js/image-lightbox/imageLightboxUtils";

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

checkForLightGallery(initLightGallery);
