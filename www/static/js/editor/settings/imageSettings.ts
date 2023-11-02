/**
 * Returns the settings for the Images
 * @todo - there's currently an issue with preserving extra attributes on the imageBlock element
 * as imageInline appears to work fine, all images are currently set to imageInline, so all imageBlock
 * related functionality is currently disabled
 * @returns { object } - the image settings
 */
export function getImageSettings() {
    return {
        image: {
            // image setup: https://ckeditor.com/docs/ckeditor5/latest/features/images/images-overview.html
            toolbar: ["linkImage", "|", "resizeImage", "fullWidth"],
            // toolbar: [
            //     "imageTextAlternative",
            //     // "toggleImageCaption",
            //     "linkImage",
            //     "|",
            //     "imageStyle:inline",
            //     {
            //         name: "imageStyle:wrapText",
            //         title: "Wrap text",
            //         items: ["imageStyle:alignLeft", "imageStyle:alignRight"],
            //         defaultItem: "imageStyle:alignLeft",
            //     },
            //     {
            //         name: "imageStyle:breakText",
            //         title: "Break text",
            //         items: ["imageStyle:alignBlockLeft", "imageStyle:block", "imageStyle:alignBlockRight"],
            //         defaultItem: "imageStyle:block",
            //     },
            //     // "imageStyle:blue",
            //     "|",
            //     "resizeImage",
            //     "fullWidth",
            // ],
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
 * so the full width UI is accruate
 * @param { HTMLElement } sourceElementHTML - the sourceElement content as HTML
 * @returns { HTMLElement } - the sourceElement content as HTML with the data-schoolboxresource-fullwidth attribute added to any images that have the class full-width
 */
export function addFullWidthAttributes(sourceElementHTML: HTMLElement) {
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
 * @param { HTMLElement } sourceElementHTML - the sourceElement content as HTML
 * @returns { HTMLElement } - the sourceElement content as HTML with the height style property removed from any video thumbnails
 */
export function removeHeightFromVideoThumbs(sourceElementHTML: HTMLElement) {
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
