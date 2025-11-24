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
 * Determines if URLs have significantly changed by comparing paths, hashes, and query parameters
 * Can be used to determine for example if we are just loading more results,
 * in which case it's not considered a significant change.
 * @param oldUrl - the previous URL
 * @param newUrl - the new URL
 * @param ignoreParams - array of parameter names to ignore when comparing URLs
 * @returns - true if the URLs have changed significantly
 */
export function isSignificantUrlChange(oldUrl: string, newUrl: string, ignoreParams: string[] = []): boolean {
    try {
        const oldUrlObj = new URL(oldUrl, window.location.origin);
        const newUrlObj = new URL(newUrl, window.location.origin);

        // If pathname or hash changed, URLs have definitely changed
        if (oldUrlObj.pathname !== newUrlObj.pathname || oldUrlObj.hash !== newUrlObj.hash) {
            return true;
        }

        // Compare query parameters excluding ignored parameters
        const oldParams = new URLSearchParams(oldUrlObj.search);
        const newParams = new URLSearchParams(newUrlObj.search);

        // Remove ignored parameters from both for comparison
        ignoreParams.forEach((param) => {
            oldParams.delete(param);
            newParams.delete(param);
        });

        // Compare parameters in an order-independent way
        // First check if they have the same number of parameters
        const oldKeys = Array.from(oldParams.keys());
        const newKeys = Array.from(newParams.keys());

        if (oldKeys.length !== newKeys.length) {
            return true;
        }

        // Check if all parameters and values match
        return oldKeys.some((key) => oldParams.get(key) !== newParams.get(key));
    } catch (error) {
        // If URL parsing fails, be safe and assume URLs have changed
        return true;
    }
}
