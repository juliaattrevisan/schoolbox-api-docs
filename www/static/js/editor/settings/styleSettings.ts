import type { StyleConfig } from "@ckeditor/ckeditor5-style";

/**
 * Returns the settings for the Styles dropdown
 * @returns - the style settings
 */
export function getStyleSettings(): { style: StyleConfig } {
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
                    element: "p",
                    classes: ["highlighted-note"],
                },
                {
                    name: "Banner",
                    element: "p",
                    classes: ["sb-ck-banner"],
                },
                {
                    name: "Info Callout",
                    element: "p",
                    classes: ["sb-ck-callout--info"],
                },
                {
                    name: "Warning Callout",
                    element: "p",
                    classes: ["sb-ck-callout--warning"],
                },
                {
                    name: "Success Callout",
                    element: "p",
                    classes: ["sb-ck-callout--success"],
                },
                {
                    name: "Target Callout",
                    element: "p",
                    classes: ["sb-ck-callout--target"],
                },
                {
                    name: "Question Callout",
                    element: "p",
                    classes: ["sb-ck-callout--question"],
                },
                {
                    name: "Sticky Note",
                    element: "p",
                    classes: ["sb-ck-sticky-note"],
                },
                {
                    name: "Construction",
                    element: "p",
                    classes: ["sb-ck-construction"],
                },
                { name: "Code block", element: "p", classes: ["sb-ck-code-block"] },
            ],
        },
    };
}
