
/**
 * returns an integer indicating the position of the 
 * passed element relative to the original collection
 * replacement for $(el).index
 *
 * @param el the DOM element to find the index of
 * 
 * @return index of the DOM element
 */
export const getIndex = (el) => {
    if (!el) {
        return -1;
    }
    let i = 0;
    while (el = el.previousElementSibling) {
        i++;
    }
    return i;
}


/**
 * empty the element of children
 * replacement for $(el).empty
 *
 * @param el the DOM element to empty
 * 
 */
export const empty = (el) => {
    while (el.firstChild) {
        el.removeChild(el.firstChild);
    }
}