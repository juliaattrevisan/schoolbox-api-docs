/**
 * Get the current url; these redirects are used after backend processing
 * so you can return to your current location afterwards
 *
 * Use the base64encoded version of the url's pathname, so it can work in
 * conjunction with Class Redirect in the back end
 * @param url - the url to go to, or leave blank to retrieve just the returnUrl param
 * @returns - The returnUrl parameter with the current path encoded in base64
 */
export function getReturnUrl(url?: string | undefined): string {
    const currentPath = `${window.location.pathname}${window.location.search}${window.location.hash}`;
    const returnUrl = `returnUrl=${window.btoa(currentPath)}`;

    if (url) {
        // Pick the correct separator in case the url already has a query string
        return `${url}${url.includes("?") ? "&" : "?"}${returnUrl}`;
    }

    return returnUrl;
}

/**
 * Checks if a URL is new by comparing the pathname and hash, ignoring query parameters
 * @param oldUrl - The original URL to compare
 * @param newUrl - The new URL to compare
 * @returns - True if the URLs are different, false if they are the same
 */
export function isNewUrl(oldUrl: string, newUrl: string): boolean {
    try {
        const oldUrlObj = new URL(oldUrl, window.location.origin);
        const newUrlObj = new URL(newUrl, window.location.origin);
        // Compare pathname and hash, ignoring search parameters
        return oldUrlObj.pathname !== newUrlObj.pathname || oldUrlObj.hash !== newUrlObj.hash;
    } catch {
        // If URL parsing fails, consider them different
        return true;
    }
}
