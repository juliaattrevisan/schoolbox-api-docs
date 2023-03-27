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
                    classes: ["schoolbox-ck-banner"],
                },
                {
                    name: "Info Callout",
                    element: "p",
                    classes: ["schoolbox-ck-callout--info"],
                },
                {
                    name: "Warning Callout",
                    element: "p",
                    classes: ["schoolbox-ck-callout--warning"],
                },
                {
                    name: "Success Callout",
                    element: "p",
                    classes: ["schoolbox-ck-callout--success"],
                },
                {
                    name: "Target Callout",
                    element: "p",
                    classes: ["schoolbox-ck-callout--target"],
                },
                {
                    name: "Question Callout",
                    element: "p",
                    classes: ["schoolbox-ck-callout--question"],
                },
                {
                    name: "Sticky Note",
                    element: "p",
                    classes: ["schoolbox-ck-sticky-note"],
                },
                {
                    name: "Construction",
                    element: "p",
                    classes: ["schoolbox-ck-construction"],
                },
                { name: "Code block", element: "p", classes: ["schoolbox-ck-code-block"] },
            ],
        },
    };
}
