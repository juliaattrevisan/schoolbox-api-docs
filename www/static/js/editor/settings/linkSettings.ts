import type { LinkConfig } from "@ckeditor/ckeditor5-link/src/linkconfig";

/**
 * Returns the settings for the links
 * @returns {{ link: LinkConfig }} - the links settings
 */
export function getLinkSettings(): { link: LinkConfig } {
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

/**
 * Add the prefix to the id of any elements that have an id already set that doesn't start with the prefix.
 * We use this so that we can ensure that the id is unique and doesn't clash with any other ids on the page.
 * The id attribute is allowed on the elements specified so that page anchors can be added within the editor to link to sections
 * of content within the same page.
 * @param { HTMLElement } sourceElementHTML - the sourceElement content as HTML
 * @returns { HTMLElement } -
 */
export function addPrefixToElementIDs(sourceElementHTML: HTMLElement) {
    // these elements are allowed to have an id, specified in www/static/js/editor/settings/htmlSupportSettings.ts
    const elementsWithId = sourceElementHTML.querySelectorAll(
        "div[id], p[id], h2[id], h3[id], h4[id], h5[id], span[id]",
    );
    const prefix = "page-anchor-";
    // filter out elements that already have an id starting with the prefix
    const filteredElements = Array.from(elementsWithId).filter((element) => {
        return !element.id.startsWith(prefix);
    });
    // add the prefix to the id of each element that doesn't already have it
    filteredElements.forEach((element) => {
        element.setAttribute("id", `${prefix}${element.id}`);
    });
    return sourceElementHTML;
}
