/**
 * Instantiate all custom web components that leverage Vue as their rendering engine
 * More info on web components here: https://developer.mozilla.org/en-US/docs/Web/API/Web_components
 * And using Vue with web components here: https://vuejs.org/guide/extras/web-components.html
 */

import SbxFreeStyle from "@schoolboxVue/components/generic/sbx-free-style.ce.vue";
import SbxPopOut from "@schoolboxVue/components/popOut/sbx-pop-out.ce.vue";
import SbxIcon from "@schoolboxVue/components/icon/sbx-icon.ce.vue";
import SbxLabel from "@schoolboxVue/components/label/sbx-label.ce.vue";
import SbxLabelPastoralPoints from "@schoolboxVue/components/label/sbx-label-pastoral-points.ce.vue";
import SbxSearchSelect from "@schoolboxVue/components/searchSelect/sbx-search-select.ce.vue";

/**
 * Register all web components here
 */
export function register(): void {
    // we only need to register the web components once
    if (window.customElements.get("sbx-free-style") === undefined) {
        const SbxFreeStyleCE = window.Vue.defineCustomElement(SbxFreeStyle);
        window.customElements.define("sbx-free-style", SbxFreeStyleCE);
        const SbxPopOutCE = window.Vue.defineCustomElement(SbxPopOut);
        window.customElements.define("sbx-pop-out", SbxPopOutCE);
        const SbxIconCE = window.Vue.defineCustomElement(SbxIcon);
        window.customElements.define("sbx-icon", SbxIconCE);
        const SbxLabelCE = window.Vue.defineCustomElement(SbxLabel);
        window.customElements.define("sbx-label", SbxLabelCE);
        const SbxLabelPastoralPointsCE = window.Vue.defineCustomElement(SbxLabelPastoralPoints);
        window.customElements.define("sbx-label-pastoral-points", SbxLabelPastoralPointsCE);
        const SbxSearchSelectCE = window.Vue.defineCustomElement(SbxSearchSelect);
        window.customElements.define("sbx-search-select", SbxSearchSelectCE);
    }
}

register();
