/**
 * Returns the settings for the headings dropdown
 *
 * @returns { object } - the headings settings
 */
export function getHeadingSettings() {
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

/**
 * CKEditor5 does not allow headings to exit within <li> elements, this wraps any heading/pre elements within lists
 * in a <span> or <tt> element to mimic the behaviour of CKEditor4
 *
 * @param { HTMLElement } sourceElementHTML - the sourceElement content as HTML
 * @returns { HTMLElement } - the html with the listed headings wrapped in a span element
 */
export function fixListedHeadings(sourceElementHTML: HTMLElement): HTMLElement {
    let str = sourceElementHTML.innerHTML;
    str = str.replace(/<li>\s*<h2>(.*?)<\/h2>\s*<\/li>/gi, '<li><span style="font-size:1.125rem">$1</span></li>');
    str = str.replace(/<li>\s*<h3>(.*?)<\/h3>\s*<\/li>/gi, '<li><span style="font-size:1.0625rem;">$1</span></li>');
    str = str.replace(/<li>\s*<h4>(.*?)<\/h4>\s*<\/li>/gi, '<li><span style="font-size:1rem;">$1</span></li>');
    str = str.replace(/<li>\s*<pre>(.*?)<\/pre>\s*<\/li>/gi, '<li><tt class="">$1</tt></li>');
    sourceElementHTML.innerHTML = str;
    return sourceElementHTML;
}
