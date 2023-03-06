/**
 * Returns the settings for the Images
 *
 * @returns { object } - the image settings
 */
export function getImageSettings() {
    return {
        image: {
            // image setup: https://ckeditor.com/docs/ckeditor5/latest/features/images/images-overview.html
            toolbar: [
                "imageTextAlternative",
                "toggleImageCaption",
                "linkImage",
                "|",
                "imageStyle:inline",
                {
                    name: "imageStyle:wrapText",
                    title: "Wrap text",
                    items: ["imageStyle:alignLeft", "imageStyle:alignRight"],
                    defaultItem: "imageStyle:alignLeft",
                },
                {
                    name: "imageStyle:breakText",
                    title: "Break text",
                    items: ["imageStyle:alignBlockLeft", "imageStyle:block", "imageStyle:alignBlockRight"],
                    defaultItem: "imageStyle:block",
                },
                "imageStyle:side",
                "|",
                "resizeImage",
            ],
        },
    };
}
