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
