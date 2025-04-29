/*
 * Array tools
 */

/**
 * To move an item in an array to a new index
 * https://stackoverflow.com/questions/5306680/move-an-array-element-from-one-array-position-to-another
 * @param array - array to move item in
 * @param oldIndex - index of item to move
 * @param newIndex - index to move item to
 * @returns - the array with the item moved
 */
export function moveArrayItem(array: unknown[], oldIndex: number, newIndex: number): unknown[] {
    if (oldIndex < 0 || oldIndex >= array.length) {
        return array;
    }
    if (newIndex < 0 || newIndex >= array.length) {
        return array;
    }
    if (newIndex >= array.length) {
        let k = newIndex - array.length + 1;
        while (k > 0) {
            k -= 1;
            array.push(undefined);
        }
    }
    array.splice(newIndex, 0, array.splice(oldIndex, 1)[0]);
    return array;
}

/**
 * To check if an array is made up of numbers
 * @param arr - array to check
 * @returns - true if all items in the array are numbers
 */
export function isArrayOfNumbers(arr: unknown): arr is number[] {
    return Array.isArray(arr) && arr.every((item) => typeof item === "number");
}

/**
 * Get the keys of an object as a union of string literals
 */
export type KeysOf<O> = Exclude<
    O extends readonly unknown[]
        ? number extends O["length"]
            ? `${number}`
            : keyof O & `${number}`
        : {
              [K in keyof O]: K extends string ? K : K extends number ? `${K}` : never;
          }[keyof O],
    undefined // to avoid Partial<T> returning undefined
>;

/**
 * Mimics the result of Object.keys(...), but types the result as a union of string literals
 * @param o - The object to get the keys of
 * @returns - The keys of the object as a union of string literals
 */
export function keysOf<O extends object>(o: O): KeysOf<O>[] {
    return Object.keys(o) as KeysOf<O>[];
}

/**
 * Mimics the result of Object.fromEntries(...), but correctly types the result as a Record<K, V>
 * @param entries - An iterable object that contains key-value entries for properties and methods
 * @returns - The object created from the iterable
 */
export function fromEntriesOf<K extends PropertyKey, V>(entries: Iterable<readonly [K, V]>): Record<K, V> {
    return Object.fromEntries(entries) as Record<K, V>;
}
