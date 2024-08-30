import type { LightGallery } from "lightgallery/lightgallery";
import type { LightGallerySettings } from "lightgallery/lg-settings";

export const IMAGE_LIGHTBOX_CREATE = "IMAGE-LIGHTBOX__CREATE";

export interface ImageLightboxCreateEvent extends CustomEvent {
    detail: {
        targetKey: string;
        options: LightGallerySettings;
        onCreated?: (gallery: LightGallery) => void;
    };
}

export interface ImageLightboxDocumentEventHandlerMap {
    [IMAGE_LIGHTBOX_CREATE]: ImageLightboxCreateEvent;
}
