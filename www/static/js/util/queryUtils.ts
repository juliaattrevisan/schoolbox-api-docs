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
    const result: NestedObject = {};
    /**
     * Recursively converts an object to a collection of key-value pairs
     * @param {Record<string, string>} curr - The current object
     * @param {string} keyPath - The path of the current key
     */
    function recurse(curr: NestedObject, keyPath = "") {
        Object.keys(curr).forEach((key) => {
            if (Object.prototype.hasOwnProperty.call(curr, key)) {
                const newKeyPath = keyPath ? `${keyPath}[${key}]` : key;
                if (typeof curr[key] === "object" && !Array.isArray(curr[key])) {
                    // Recursively call the function for nested objects
                    recurse(curr[key], newKeyPath);
                } else {
                    // Assign the key-value pair to the result object
                    result[newKeyPath] = curr[key];
                }
            }
        });
    }
    recurse(obj);
    return result;
}
