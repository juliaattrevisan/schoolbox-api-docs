/**
 * Serialize an object into a query string.
 *
 * @param object
 * @return {string}
 */
export default function (object) {
    let qsParts = [];
    if (typeof object === 'object') {
        Object.keys(object).forEach(function (key) {
            let val = object[key];
            qsParts.push(encodeURIComponent(key) + '=' + encodeURIComponent(val));
        });
    }
    return qsParts.join('&');
}
