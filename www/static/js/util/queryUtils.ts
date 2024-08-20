/**
 * Any utility functions related to processing query values for adding to requests
 */

type NestedObject = {
    [key: string]: string | NestedObject;
};

/**
 * Takes an object and converts it to a collection of key-value pairs, with object depth represented by array bracket notation
 * @param {NestedObject} obj - The object to be converted to key-value pairs
 * @returns {Record<string, string>} - The key-value pairs
 */
export function getKeyValuePairs(obj: NestedObject) {
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
                    // If the value is a string, add it to the result
                    result[newKeyPath] = value;
                } else if (typeof value === "number") {
                    // If the value is a number, convert it to a string and add it to the result
                    result[newKeyPath] = (value as number).toString();
                } else if (Array.isArray(value)) {
                    // If the value is an array, loop through the array and add an entry for each element
                    value.forEach((element, index) => {
                        const arrayKeyPath = `${newKeyPath}[${index}]`;
                        if (typeof element === "object" && element !== null) {
                            // Recursively call the function for nested objects
                            recurse(element, arrayKeyPath);
                        } else if (typeof element === "string") {
                            // If the element is a string, add it to the result
                            result[arrayKeyPath] = element;
                        } else if (typeof element === "number") {
                            // If the element is a number, convert it to a string and add it to the result
                            result[arrayKeyPath] = (element as number).toString();
                        } else if (element === null) {
                            // If the element is null, add an empty string to the result
                            result[arrayKeyPath] = "";
                        }
                    });
                } else if (value === null) {
                    // If the value is null, add an empty string to the result
                    result[newKeyPath] = "";
                }
            }
        });
    }
    recurse(obj);
    return result;
}

/**
 * Takes an object and converts it to a search string
 * @param {NestedObject} obj - The object to be converted to a search param string
 * @returns {string} - the URLSearchParams object as a string
 */
export function getSearchString(obj: NestedObject) {
    // first convert the object to key-value pairs
    const keyValuePairs = getKeyValuePairs(obj);
    const searchParams = new URLSearchParams();
    const map = new Map(Object.entries(keyValuePairs));
    // then append each key-value pair to the searchParams object
    map.forEach((value, key) => {
        searchParams.append(key, value);
    });
    // then return the searchParams object as a string
    return searchParams.toString();
}

/**
 * Takes an object and converts it to a FormData
 * @param {NestedObject} obj - The object to be converted to a FormData
 * @returns {string} - the FormData object
 */
export function getFormData(obj: NestedObject) {
    // first convert the object to key-value pairs
    const keyValuePairs = getKeyValuePairs(obj);
    const formData = new FormData();
    const map = new Map(Object.entries(keyValuePairs));
    // then append each key-value pair to the searchParams object
    map.forEach((value, key) => {
        formData.append(key, value);
    });
    // then return the searchParams object as a string
    console.warn("formData", formData.values());
    return formData;
}
