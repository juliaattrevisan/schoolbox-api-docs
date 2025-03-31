/**
 * The types at https://www.npmjs.com/package/@types/locutus are all just "any"
 * so this provides a proper type definition for the strtotime function
 */

declare module "locutus/php/datetime/strtotime" {
    /**
     * https://locutus.io/php/datetime/strtotime/index.html
     * @param text - The natural language date input
     * @param now - The timestamp to use as a base for relative dates
     * @returns - The timestamp or false if the input could not be parsed
     */
    function strtotime(text?: string, now?: number): number | false;
    export default strtotime;
}
