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
 * @param { HTMLTextAreaElement } textarea - the textarea the CKEditor will replace
 * @returns { string } - the html with the data attribute added
 */
export function addRelToNewTabLinks(textarea: HTMLTextAreaElement) {
    const tempDiv = document.createElement("div");
    tempDiv.innerHTML = textarea.value;
    const links = tempDiv.querySelectorAll("a");
    links.forEach((link) => {
        if (link.getAttribute("target") === "_blank") {
            link.setAttribute("rel", "noopener noreferrer");
        }
    });
    return tempDiv.innerHTML;
}
