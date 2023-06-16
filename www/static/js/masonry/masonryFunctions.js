
/**
 * To retrieve the component id
 *
 * @param { Element }
 *
 * @returns { String }
 */
export function getComponentID(component) {
    return component.id.replace("component", ""); //strip the 'component' at the start to just get the id number
}
