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
window.sbProps.lightGalleries = {};

document.addEventListener("IMAGE-LIGHTBOX__CREATE", (e) => {
    console.log(e);
    let targetElement = document.querySelector("[data-image-lightbox='" + e.detail.targetKey + "']") as HTMLElement;
    if (targetElement === null) {
        targetElement = document.createElement("div");
        targetElement.setAttribute("data-image-lightbox", e.detail.targetKey);
        targetElement.style.display = "none";
        document.body.appendChild(targetElement);
    }

    if (window.sbProps.lightGalleries[e.detail.targetKey]) {
        window.sbProps.lightGalleries[e.detail.targetKey].destroy(true);
    }

    targetElement.setAttribute("data-lightbox-loaded", "true");
    window.sbProps.lightGalleries[e.detail.targetKey] = window.sbProps.lightGallery(
        targetElement,
        Object.assign(imageLightboxDefaults, e.detail.options)
    );

    document.dispatchEvent(new CustomEvent("IMAGE-LIGHTBOX__CREATED", { detail: { targetKey: e.detail.targetKey } }));
    if (e.detail.onCreated) {
        e.detail.onCreated(window.sbProps.lightGalleries[e.detail.targetKey]);
    }
});

document.dispatchEvent(new CustomEvent("IMAGE-LIGHTBOX__LOADED"));
