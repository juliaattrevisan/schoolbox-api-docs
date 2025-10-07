import type { LightGallery } from "lightgallery/lightgallery";
import type { LightGallerySettings } from "lightgallery/lg-settings";
import type { GalleryItem } from "lightgallery/lg-utils";
import { ResourceType } from "@js/resource/resourceUtils";
import type { StorageFile } from "@js/resource/resourceUtils";

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

/**
 * Turn a storage file into a previewable item in a lightbox
 * TODO: Currenty unimplemented for anything except images, use preview.js for other types
 * @param storageFile - the file to convert
 * @param filename - optionally override the filename
 * @returns The gallery items to show in the lightbox, or null if the file is not previewable
 */
export function getStorageGallerySlide(storageFile: StorageFile, filename?: string): GalleryItem | null {
    if (storageFile.type === ResourceType.IMAGE) {
        return {
            src: `${storageFile._links.image}&size=maxsize`,
            thumb: `${storageFile._links.image}&size=square128`,
            subHtml: filename || storageFile.name || undefined,
        };
    }

    // all other types are currently unsupported thru this method, use preview.js instead or add here
    return null;
}

/**
 * Turns a list of several storage items into gallery items for a lightbox
 * @param storageFiles - the files to convert'
 * @param selectedFileHash - the hash of the file that should be selected initially
 * @returns The gallery items that can be shown, as well as the index of the selected item
 */
export function getStorageGallerySlides(
    storageFiles: StorageFile[],
    selectedFileHash?: string,
): { slides: GalleryItem[]; selectedIndex: number } {
    const slides: GalleryItem[] = [];
    let selectedIndex = 0;

    for (const file of storageFiles) {
        const slide = getStorageGallerySlide(file);
        if (slide !== null) {
            slides.push(slide);

            if (file.hash === selectedFileHash) {
                selectedIndex = slides.length - 1;
            }
        }
    }

    return { slides, selectedIndex };
}

/**
 * Create a lightbox with arbitrary slides
 * @param targetKey - The key to identify the lightbox instance
 * @param slides - The slides to show in the lightbox
 * @param options - The options to pass to lightGallery
 * @returns the LightGallery instance after it has been created
 */
export async function createLightbox(
    targetKey: string,
    slides: GalleryItem[],
    options: Partial<Omit<LightGallerySettings, "dynamic" | "dynamicEl">> = {},
): Promise<LightGallery> {
    return new Promise((resolve) => {
        const event = new CustomEvent<ImageLightboxCreateEvent["detail"]>(IMAGE_LIGHTBOX_CREATE, {
            detail: {
                targetKey,
                options: {
                    ...options,
                    dynamic: true,
                    dynamicEl: slides,
                },
                onCreated: resolve,
            },
        });

        document.dispatchEvent(event);
    });
}
