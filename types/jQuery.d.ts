/**
 * We add jQuery types so we can access jQuery related methods within TS files:
 * https://www.typescriptlang.org/docs/handbook/modules.html#default-exports
 * When jQuery is used within a TS file, add the following line:
 * import $ from "jquery";
 * Which will add support for jQuery within that file (and strip the types from the final JS file)
 */

declare const $: jQuery;
export default $;
