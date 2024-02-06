/**
 * Returns the settings for the Styles dropdown
 * @returns { object } - the style settings
 */
export function getStyleSettings() {
    return {
        style: {
            // the list of styles provided: https://ckeditor.com/docs/ckeditor5/latest/features/style.html
            definitions: [
                {
                    name: "Small",
                    element: "small",
                    classes: [""],
                },
                {
                    name: "Typewriter",
                    element: "tt",
                    classes: [""],
                },
                {
                    name: "Highlighted Note",
                    element: "span",
                    classes: ["highlighted-note"],
                },
                {
                    name: "Banner",
                    element: "span",
                    classes: ["sb-ck-banner"],
                },
                {
                    name: "Info Callout",
                    element: "span",
                    classes: ["sb-ck-callout--info"],
                },
                {
                    name: "Warning Callout",
                    element: "span",
                    classes: ["sb-ck-callout--warning"],
                },
                {
                    name: "Success Callout",
                    element: "span",
                    classes: ["sb-ck-callout--success"],
                },
                {
                    name: "Target Callout",
                    element: "span",
                    classes: ["sb-ck-callout--target"],
                },
                {
                    name: "Question Callout",
                    element: "span",
                    classes: ["sb-ck-callout--question"],
                },
                {
                    name: "Sticky Note",
                    element: "span",
                    classes: ["sb-ck-sticky-note"],
                },
                {
                    name: "Construction",
                    element: "span",
                    classes: ["sb-ck-construction"],
                },
                { name: "Code block", element: "p", classes: ["sb-ck-code-block"] },
            ],
        },
    };
}
