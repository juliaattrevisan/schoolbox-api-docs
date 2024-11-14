/**
 * Get the current url; these redirects are used after backend processing
 * so you can return to your current location afterwards
 *
 * Use the base64encoded version of the url's pathname, so it can work in
 * conjunction with Class Redirect in the back end
 * @param url - the url to go to, or leave blank to retrieve just the returnUrl param
 * @returns - the encoded returnUrl
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
