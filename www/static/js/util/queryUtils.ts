/**
 * Any utility functions related to processing query values for adding to requests
 */

type NestedObject = {
    [key: string]: string | NestedObject;
};

/**
 * Takes an object and converts it to a collection of key-value pairs, with object depth represented by array notation
 * @param {NestedObject} obj - The object to be converted to key-value pairs
 * @returns {Record<string, string>} - The key-value pairs
 */
export function createKeyValuePairs(obj: NestedObject) {
    const result: Record<string, string> = {};
    /**
     * Recursively converts an object to a collection of key-value pairs
     * @param {Record<string, string>} curr - The current object
     * @param {string} keyPath - The path of the current key
     */
    function recurse(curr: NestedObject, keyPath = "") {
        Object.keys(curr).forEach((key) => {
            if (Object.prototype.hasOwnProperty.call(curr, key)) {
                const newKeyPath = keyPath ? `${keyPath}[${key}]` : key;
                const value = curr[key];
                if (typeof value === "object" && value !== null && !Array.isArray(value)) {
                    // Recursively call the function for nested objects
                    recurse(value, newKeyPath);
                } else if (typeof value === "string") {
                    // Otherwise assign the key-value pair to the result object
                    result[newKeyPath] = value;
                }
            }
        });
    }
    recurse(obj);
    return result;
}

/**
 * Takes an object and converts it to a query string
 * @param {NestedObject} obj - The object to be converted to a query string
 * @returns {string} - The query string
 */
export function createQueryString(obj: NestedObject) {
    // first convert the object to key-value pairs
    const keyValuePairs = createKeyValuePairs(obj);
    const searchParams = new URLSearchParams();
    const map = new Map(Object.entries(keyValuePairs));
    // then append each key-value pair to the searchParams object
    map.forEach((key, value) => {
        searchParams.append(key, value);
    });
    // then return the searchParams object as a string
    return searchParams.toString();
}
