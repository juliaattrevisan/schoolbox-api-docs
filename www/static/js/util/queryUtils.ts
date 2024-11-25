/**
 * Any utility functions related to processing query values for adding to requests
 */

import { isArrayOfNumbers } from "./arrayUtils";

type NestedObject = {
    [key: string]: string | NestedObject | number[];
};

/**
 * Checks if a value is a nested object
 * @param value - The value to be checked
 * @returns - Whether the value is a nested object
 */
function isValueNestedObject(value: unknown): value is NestedObject {
    return (typeof value === "object" && value !== null && !Array.isArray(value)) || isArrayOfNumbers(value);
}

/**
 * Takes a value and converts it to a string
 * @param value - The value to be converted to a string
 * @returns - The value as a string
 */
function setValue(value: unknown): string {
    if (typeof value === "string") {
        return value;
    }
    if (typeof value === "number" || typeof value === "boolean") {
        return value.toString();
    }
    return "";
}

/**
 * Takes an object and converts it to a collection of key-value pairs,
 * with object depth represented by array bracket notation
 * @param obj - The object to be converted to key-value pairs
 * @returns - The key-value pairs
 */
export function getKeyValuePairs(obj: NestedObject): Record<string, string> {
    const result: Record<string, string> = {};
    /**
     * Recursively converts an object to a collection of key-value pairs
     * @param curr - The current object
     * @param [keyPath] - The path of the current key
     */
    function recurse(curr: NestedObject, keyPath?: string): void {
        Object.keys(curr).forEach((key) => {
            if (Object.prototype.hasOwnProperty.call(curr, key)) {
                const newKeyPath = keyPath ? `${keyPath}[${key}]` : key;
                const value = curr[key];
                if (isValueNestedObject(value)) {
                    // Recursively call the function for nested objects
                    recurse(value, newKeyPath);
                } else if (Array.isArray(value)) {
                    // If the value is an array, loop through the array and add an entry for each valueWithin
                    value.forEach((valueWithin: unknown, index) => {
                        const arrayKeyPath = `${newKeyPath}[${index}]`;
                        if (isValueNestedObject(valueWithin)) {
                            // Recursively call the function for nested objects
                            recurse(valueWithin, arrayKeyPath);
                        } else {
                            result[arrayKeyPath] = setValue(valueWithin);
                        }
                    });
                } else {
                    result[newKeyPath] = setValue(value);
                }
            }
        });
    }
    recurse(obj);
    return result;
}

/**
 * Takes an object and converts it to a search string
 * @param obj - The object to be converted to a search param string
 * @returns - the URLSearchParams object as a string
 */
export function getSearchString(obj: NestedObject): string {
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
 * @param obj - The object to be converted to a FormData
 * @returns - the FormData object
 */
export function getFormData(obj: NestedObject): FormData {
    // first convert the object to key-value pairs
    const keyValuePairs = getKeyValuePairs(obj);
    const formData = new FormData();
    const map = new Map(Object.entries(keyValuePairs));
    // then append each key-value pair to the searchParams object
    map.forEach((value, key) => {
        formData.append(key, value);
    });
    // then return the searchParams object as a string
    return formData;
}
