/**
 * Returns the settings for the Styles dropdown
 *
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
                /* {
                    name: "Computer Code",
                    element: "code",
                    classes: [""],
                }, */
                {
                    name: "Highlighted Note",
                    element: "span",
                    classes: ["highlighted-note"],
                },
                {
                    name: "Banner",
                    element: "p",
                    classes: ["ck-s-banner"],
                },
                {
                    name: "Info Callout",
                    element: "p",
                    classes: ["ck-s-callout--info"],
                },
                {
                    name: "Warning Callout",
                    element: "p",
                    classes: ["ck-s-callout--warning"],
                },
                {
                    name: "Success Callout",
                    element: "p",
                    classes: ["ck-s-callout--success"],
                },
                {
                    name: "Target Callout",
                    element: "p",
                    classes: ["ck-s-callout--target"],
                },
                {
                    name: "Question Callout",
                    element: "p",
                    classes: ["ck-s-callout--question"],
                },
                {
                    name: "Sticky Note",
                    element: "p",
                    classes: ["ck-s-sticky-note"],
                },
                {
                    name: "Construction",
                    element: "p",
                    classes: ["ck-s-construction"],
                },
                { name: "Code block", element: "p", classes: ["ck-s-code-block"] },
            ],
        },
    };
}
