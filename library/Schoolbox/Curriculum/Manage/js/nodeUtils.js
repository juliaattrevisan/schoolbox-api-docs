/**
 * Various utils related to managing curriculum nodes
 */

/**
 * To just return the node containing the specified id
 * We recursively loop through the object until we find the
 * matched object or undefined if not found
 *
 * @param { array }
 * @param { number }
 */
 export function findById (array, id) {
    for (const item of array) {
        if (item.id === id) {
            return item;
        }
        if (item.children?.length) {
            const innerResult = findById(item.children, id);
            if (innerResult) {
                return innerResult;
            }
        }
    }
}

/**
 * Sort the passed through curriculum nodes by their .name property
 *
 * @param { array }
 */
export function sortByName (array, id) {
    array.sort((a, b) => {
        return a.name.localeCompare(b.name, undefined, {
            numeric: true,
            sensitivity: 'base'
        });
    });
}

/**
 * To return an array with any objects containing the specified id removed
 * we move through the object, looping recursively through .children and remove
 * any with the id that's being removed
 *
 * @param { array }
 * @param { number }
 *
 * @returns { object }
 */
export function removeId (data, id) {
    if (data !== null) { // .children wont always be available if the node hasn't been loaded yet
        return data.reduce((previousValue, currentValue) => {
            if (currentValue.id !== id) {
                previousValue.push( { ...currentValue, children: removeId(currentValue.children, id) } );
            }
            return previousValue;
        }, []);
    } else {
        return data;
    }
}

/**
 * Recursively loop through the parents adding in a breadcrumb for each
 *
 * @param { object }
 * @param { array }
 */
export function getBreadcrumbs (node, currentBreadcrumbs) {
    if (node.hasOwnProperty('parent') && node.parent !== null) { //if there's a parent to use as a breadcrumb
        let breadcrumbs = [ { id: node.parent.id, name: node.parent.name }, ...currentBreadcrumbs ]; //add it in to the front of the list
        return breadcrumbs = getBreadcrumbs(node.parent, breadcrumbs); //and then check the next node up the list
    } else { //otherwise we must be at the top of the breadcrumb list
        return currentBreadcrumbs; //so we can return now
    }
}