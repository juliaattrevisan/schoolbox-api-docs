/**
 * Instantiate all custom web components that leverage Vue as their rendering engine
 * More info on web components here: https://developer.mozilla.org/en-US/docs/Web/API/Web_components
 * And using Vue with web components here: https://vuejs.org/guide/extras/web-components.html
 */

import WcDropDown from "./components/dropDown/wc-drop-down.ce.vue";

/**
 * Register all web components here
 */
export function register() {
    const WcDropDownCE = window.Vue.defineCustomElement(WcDropDown);
    window.customElements.define("wc-drop-down", WcDropDownCE);
}

register();
