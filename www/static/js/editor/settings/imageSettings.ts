import type { ImageConfig } from "@ckeditor/ckeditor5-image";

/**
 * Returns the settings for the Images
 * @returns - the image settings
 */
export function getImageSettings(): { image: ImageConfig } {
    return {
        image: {
            toolbar: ["linkImage", "|", "resizeImage", "fullWidth"],
            resizeUnit: "px",
            insert: {
                type: "inline",
            },
            resizeOptions: [
                {
                    name: "resizeImage:original",
                    value: null,
                    icon: "original",
                },
            ],
            styles: {
                /**
                 * We define this style so users can enforce the media to be full width in display view
                 * This class is picked up in www/static/js/editor/jquery.proxy-replacement.js to determine
                 * whether the media is displayed as full width
                 */
                options: [
                    {
                        name: "fullWidth",
                        modelElements: ["imageInline"],
                        title: "Full width",
                        icon: "full",
                        className: "full-width",
                    },
                ],
            },
        },
    };
}

/**
 * Add the data-schoolboxresource-fullwidth attribute to any images that have the class full-width
 * This is so any images marked full width in CKEditor 4 are also marked full width in CKEditor 5,
 * so the full width UI is accurate
 * @param sourceElementHTML - the sourceElement content as HTML
 * @returns - the sourceElement content as HTML with the data-schoolboxresource-fullwidth attribute added to any images that have the class full-width
 */
export function addFullWidthAttributes(sourceElementHTML: HTMLElement): HTMLElement {
    const images = sourceElementHTML.querySelectorAll("img");
    images.forEach((image) => {
        if (image.classList.contains("full-width")) {
            image.setAttribute("data-schoolboxresource-fullwidth", "true");
        }
    });
    return sourceElementHTML;
}

/**
 * The height style property was added to video thumbnails in CKEditor 4, however in CKEditor 5
 * it interferes with the resize widget and can create display issues if the thumbnail is resized.
 * @param sourceElementHTML - the sourceElement content as HTML
 * @returns - the sourceElement content as HTML with the height style property removed from any video thumbnails
 */
export function removeHeightFromVideoThumbs(sourceElementHTML: HTMLElement): HTMLElement {
    const images = sourceElementHTML.querySelectorAll("img");
    images.forEach((image) => {
        if (
            image.hasAttribute("data-schoolboxresource-type") &&
            image.getAttribute("data-schoolboxresource-type") === "video" &&
            image.style.height !== ""
        ) {
            image.style.height = "";
        }
    });
    return sourceElementHTML;
}
