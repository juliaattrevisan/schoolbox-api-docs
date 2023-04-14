/**
 * Returns the settings for the links
 *
 * @returns { object } - the links settings
 */
export function getLinkSettings() {
    return {
        link: {
            // see https://ckeditor.com/docs/ckeditor5/latest/features/link.html
            decorators: {
                openInNewTab: {
                    mode: "manual",
                    label: "Open in a new tab",
                    defaultValue: false,
                    attributes: {
                        target: "_blank",
                        rel: "noopener noreferrer",
                    },
                },
                toggleDownloadable: {
                    mode: "manual",
                    label: "Downloadable",
                    defaultValue: false,
                    attributes: {
                        download: "file",
                    },
                },
            },
        },
    };
}

/**
 * Add the rel attribute to any links that have the target attribute set to _blank
 * This is required so the CKEditor 4 / CKeditor 5 'open in new tab' UI are kept consistent
 * As the rel attribute previously wasn't allowed in CKEditor 4, we can be sure that it won't exist in any existing data
 *
 * @param { HTMLElement } element - the element the CKEditor will replace
 * @returns { string } - the html with the data attribute added
 */
export function addRelToNewTabLinks(element: HTMLElement) {
    const parser = new DOMParser();
    const value = (element as HTMLTextAreaElement).value ? (element as HTMLTextAreaElement).value : element.innerHTML;
    const tempHTML = parser.parseFromString(value, "text/html");
    const links = tempHTML.body.querySelectorAll("a");
    links.forEach((link) => {
        if (link.getAttribute("target") === "_blank") {
            link.setAttribute("rel", "noopener noreferrer");
        }
    });
    return tempHTML.body.innerHTML;
}
