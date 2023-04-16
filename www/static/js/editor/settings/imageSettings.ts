/**
 * Returns the settings for the Images
 *
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
            insert: {
                type: "inline",
            },
            resizeOptions: [
                {
                    name: "resizeImage:original",
                    value: null,
                    icon: "original",
                },
                {
                    name: "resizeImage:25",
                    value: "25",
                    icon: "medium",
                },
                {
                    name: "resizeImage:50",
                    value: "50",
                    icon: "medium",
                },
                {
                    name: "resizeImage:75",
                    value: "75",
                    icon: "large",
                },
                {
                    name: "resizeImage:100",
                    value: "100",
                    icon: "large",
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
 *
 * @param { HTMLElement } sourceElement - the sourceElement the CKEditor will replace
 * @returns { string } - the html with the data attribute added
 */
export function addFullWidthAttributes(sourceElement: HTMLElement) {
    const parser = new DOMParser();
    // if the sourceElement is a textarea, use .value as .innerHTML will return HTML as escaped text
    const value = (sourceElement as HTMLTextAreaElement).value
        ? (sourceElement as HTMLTextAreaElement).value
        : sourceElement.innerHTML;
    const tempHTML = parser.parseFromString(value, "text/html");
    const images = tempHTML.body.querySelectorAll("img");
    images.forEach((image) => {
        if (image.classList.contains("full-width")) {
            image.setAttribute("data-schoolboxresource-fullwidth", "true");
        }
    });
    return tempHTML.body.innerHTML;
}
