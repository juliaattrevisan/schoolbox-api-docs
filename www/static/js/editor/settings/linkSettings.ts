/**
 * Returns the settings for the links
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
 * We also check for 'noreferrer noopener' as twigs 'purify' method converts 'noopener noreferrer' to 'noreferrer noopener'
 * @param { HTMLElement } sourceElementHTML - the sourceElement content as HTML
 * @returns { HTMLElement } - the sourceElement content as HTML with the rel attribute added to any links that have the target attribute set to _blank
 */
export function addRelToNewTabLinks(sourceElementHTML: HTMLElement) {
    const links = sourceElementHTML.querySelectorAll("a");
    links.forEach((link) => {
        if (
            (link.getAttribute("target") === "_blank" && link.getAttribute("rel") === null) ||
            (link.getAttribute("target") === "_blank" && link.getAttribute("rel") === "noreferrer noopener")
        ) {
            link.setAttribute("rel", "noopener noreferrer");
        }
    });
    return sourceElementHTML;
}
