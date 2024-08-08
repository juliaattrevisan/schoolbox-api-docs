import type { HeadingConfig } from "@ckeditor/ckeditor5-heading";

/**
 * Returns the settings for the headings dropdown
 * @returns {{ heading: HeadingConfig }} - the heading settings
 */
export function getHeadingSettings(): { heading: HeadingConfig } {
    return {
        heading: {
            // the list of headings provided: https://ckeditor.com/docs/ckeditor5/latest/features/headings.html
            options: [
                { model: "paragraph", title: "Paragraph", class: "ck-heading_paragraph" },
                { model: "heading2", view: "h2", title: "Heading 2", class: "ck-heading_heading2" },
                { model: "heading3", view: "h3", title: "Heading 3", class: "ck-heading_heading3" },
                { model: "heading4", view: "h4", title: "Heading 4", class: "ck-heading_heading4" },
                { model: "heading5", view: "h5", title: "Heading 5", class: "ck-heading_heading5" },
            ],
        },
    };
}
