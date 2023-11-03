/**
 * Returns the settings for the headings dropdown
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
 * Replace the heading/pre elements with a span/tt
 * @param { HTMLLIElement } listItem - the list item to replace the element in
 * @param { string } existingTag - the tag to replace
 * @param { string } newTag - the tag to replace with
 * @param { string } fontSize - the font size to set on the new element
 */
function replaceElement(listItem: HTMLLIElement, existingTag: string, newTag: string, fontSize = "") {
    const existingElement = listItem.querySelector(existingTag) as HTMLElement;
    const newElement = document.createElement(newTag);
    if (newTag === "tt") {
        // we add this dummy class so CKEditor recognises the tt element and assigns the 'Typewriter' style in the toolbar drop down
        newElement.setAttribute("class", "");
    } else {
        newElement.style.fontSize = fontSize;
    }
    newElement.innerHTML = existingElement.innerHTML;
    listItem.replaceChild(newElement, existingElement);
}

/**
 * CKEditor5 does not allow headings to exist within <li> elements, this wraps any heading/pre elements within lists
 * in a <span> or <tt> element to mimic the behaviour of CKEditor4
 * @param { HTMLElement } sourceElementHTML - the sourceElement content as HTML
 * @returns { HTMLElement } - the html with the listed headings wrapped in a span element
 */
export function fixListedHeadings(sourceElementHTML: HTMLElement): HTMLElement {
    const listItems = sourceElementHTML.querySelectorAll("li");
    listItems.forEach((listItem) => {
        if (listItem.querySelector("h2")) {
            replaceElement(listItem, "h2", "span", "1.125rem");
        }
        if (listItem.querySelector("h3")) {
            replaceElement(listItem, "h3", "span", "1.0625rem");
        }
        if (listItem.querySelector("h4")) {
            replaceElement(listItem, "h4", "span", "1rem");
        }
        if (listItem.querySelector("pre")) {
            replaceElement(listItem, "pre", "tt");
        }
    });
    return sourceElementHTML;
}
