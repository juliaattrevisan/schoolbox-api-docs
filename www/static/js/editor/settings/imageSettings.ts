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
                "fullWidth",
            ],
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
        },
    };
}
