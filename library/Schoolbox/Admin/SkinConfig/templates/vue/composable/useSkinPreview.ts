/**
 * Composable for requesting and applying the live Skin Config preview stylesheet.
 */

import type { Ref } from "vue";
import { debounce } from "es-toolkit";
import type { SkinConfigData } from "../../js/types/skinConfigTypes";

const PREVIEW_DEBOUNCE_MS = 500;
const PREVIEW_STYLE_ID = "skin-config-preview-style";
const PREVIEW_SBX_CORE_LINK_ID = "skin-config-preview-sbx-core";
const PREVIEW_ICON_FONT_STYLE_ID = "skin-config-preview-icon-fonts";
const SBX_BODY_CLASS = "sbx-body";
const SBX_CORE_PATH = "/css/core/sbx-core.css";
const ICON_FONT_VERSION = "ttda0c";

/**
 * Composable for requesting and applying the live Skin Config preview stylesheet.
 * @param configs Reactive Skin Config payload.
 * @param previewRoute Route used to request preview CSS.
 * @returns schedulePreviewRefresh callback for input/change handlers.
 */
export function useSkinPreview(
    configs: Ref<SkinConfigData>,
    previewRoute: string,
): {
    schedulePreviewRefresh: () => void;
} {
    let previewAbortController: AbortController | null = null;
    let previewRequestSequence = 0;
    let schedulePreviewRefresh: ReturnType<typeof debounce>;
    const initialToggleEnabled = configs.value.toggle.enabled;
    const initialBodyHasSbxClass = document.body.classList.contains(SBX_BODY_CLASS);
    const existingSbxCoreLinks = Array.from(
        document.querySelectorAll(`link[rel="stylesheet"][href*="${SBX_CORE_PATH}"]`),
    )
        .filter((element): element is HTMLLinkElement => element instanceof HTMLLinkElement)
        .map((element) => ({
            element,
            disabled: element.disabled,
        }));

    /**
     * Ensure the preview stylesheet element exists.
     * @returns The preview style element.
     */
    function getPreviewStyleElement(): HTMLStyleElement {
        const existingElement = document.getElementById(PREVIEW_STYLE_ID);
        if (existingElement instanceof HTMLStyleElement) {
            return existingElement;
        }

        const styleElement = document.createElement("style");
        styleElement.id = PREVIEW_STYLE_ID;
        document.head.append(styleElement);
        return styleElement;
    }

    /**
     * Ensure the preview sbx-core stylesheet link exists.
     * @returns The preview stylesheet link element.
     */
    function getPreviewSbxCoreLinkElement(): HTMLLinkElement {
        const existingElement = document.getElementById(PREVIEW_SBX_CORE_LINK_ID);
        if (existingElement instanceof HTMLLinkElement) {
            return existingElement;
        }

        const linkElement = document.createElement("link");
        linkElement.id = PREVIEW_SBX_CORE_LINK_ID;
        linkElement.rel = "stylesheet";
        linkElement.type = "text/css";
        linkElement.href = `${window.cdnPrefix}${SBX_CORE_PATH}?v=${window.siteVersion}`;
        document.head.append(linkElement);
        return linkElement;
    }

    /**
     * Ensure the preview icon font-face style element exists.
     * @returns The preview icon font-face style element.
     */
    function getPreviewIconFontStyleElement(): HTMLStyleElement {
        const existingElement = document.getElementById(PREVIEW_ICON_FONT_STYLE_ID);
        if (existingElement instanceof HTMLStyleElement) {
            return existingElement;
        }
        const styleElement = document.createElement("style");
        styleElement.id = PREVIEW_ICON_FONT_STYLE_ID;
        document.head.append(styleElement);
        return styleElement;
    }

    /**
     * Remove the injected preview stylesheet.
     */
    function removePreviewStyle(): void {
        const existingElement = document.getElementById(PREVIEW_STYLE_ID);
        if (existingElement instanceof HTMLStyleElement) {
            existingElement.remove();
        }
    }

    /**
     * Remove the preview sbx-core stylesheet link.
     */
    function removePreviewSbxCoreLink(): void {
        const existingElement = document.getElementById(PREVIEW_SBX_CORE_LINK_ID);
        if (existingElement instanceof HTMLLinkElement) {
            existingElement.remove();
        }
    }

    /**
     * Remove the preview icon font-face style element.
     */
    function removePreviewIconFontStyle(): void {
        const existingElement = document.getElementById(PREVIEW_ICON_FONT_STYLE_ID);
        if (existingElement instanceof HTMLStyleElement) {
            existingElement.remove();
        }
    }

    /**
     * @param enabled Whether the advanced UI preview is enabled.
     * @returns The icon font-face CSS.
     */
    function buildIconFontFaceCss(enabled: boolean): string {
        const fontFamily = enabled ? "sbx-icons" : "schoolbox";
        const fontFile = enabled ? "sbx-icons" : "schoolbox";
        return `@font-face {
            font-family: "${fontFamily}";
            src:
                url("${window.cdnPrefix}/fonts/${fontFile}.ttf?${ICON_FONT_VERSION}") format("truetype"),
                url("${window.cdnPrefix}/fonts/${fontFile}.woff?${ICON_FONT_VERSION}") format("woff"),
                url("${window.cdnPrefix}/fonts/${fontFile}.svg?${ICON_FONT_VERSION}#${fontFile}") format("svg");
            font-weight: normal;
            font-style: normal;
            font-display: block;
        }`;
    }

    /**
     * Restore the server-rendered baseline for body class and stylesheet links.
     */
    function restoreInitialPreviewState(): void {
        document.body.classList.toggle(SBX_BODY_CLASS, initialBodyHasSbxClass);
        existingSbxCoreLinks.forEach(({ element, disabled }) => {
            element.disabled = disabled;
        });
        removePreviewSbxCoreLink();
        removePreviewIconFontStyle();
    }

    /**
     * @param enabled Whether the advanced UI preview is enabled.
     */
    function syncTogglePreview(enabled: boolean): void {
        if (enabled === initialToggleEnabled) {
            restoreInitialPreviewState();
            return;
        }
        document.body.classList.toggle(SBX_BODY_CLASS, enabled);
        existingSbxCoreLinks.forEach(({ element }) => {
            element.disabled = !enabled;
        });
        if (enabled) {
            if (existingSbxCoreLinks.length === 0) {
                getPreviewSbxCoreLinkElement().disabled = false;
            }
        } else {
            removePreviewSbxCoreLink();
        }
        getPreviewIconFontStyleElement().textContent = buildIconFontFaceCss(enabled);
    }

    /**
     * Remove any pending or active preview request state.
     */
    function cancelPendingPreview(): void {
        schedulePreviewRefresh.cancel();
        previewAbortController?.abort();
        previewAbortController = null;
    }

    /**
     * Request the latest preview stylesheet from the backend and apply it.
     */
    async function requestPreviewCss(): Promise<void> {
        previewAbortController?.abort();
        const abortController = new AbortController();
        previewAbortController = abortController;
        previewRequestSequence += 1;
        const requestSequence = previewRequestSequence;

        try {
            const response = await fetch(previewRoute, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "text/css, application/json",
                },
                body: JSON.stringify({
                    configs: configs.value,
                }),
                signal: abortController.signal,
            });

            if (!response.ok) {
                return;
            }

            const css = await response.text();
            if (requestSequence !== previewRequestSequence || abortController.signal.aborted) {
                return;
            }

            getPreviewStyleElement().textContent = css;
        } catch (error) {
            if (!(error instanceof DOMException) || error.name !== "AbortError") {
                return;
            }
        } finally {
            if (previewAbortController === abortController) {
                previewAbortController = null;
            }
        }
    }

    /**
     * Debounce preview regeneration while the user is editing.
     */
    schedulePreviewRefresh = debounce(() => {
        void requestPreviewCss();
    }, PREVIEW_DEBOUNCE_MS);

    window.Vue.watch(
        () => configs.value.toggle.enabled,
        (enabled) => {
            syncTogglePreview(enabled);
        },
        {
            immediate: true,
        },
    );

    window.Vue.onBeforeUnmount(() => {
        cancelPendingPreview();
        removePreviewStyle();
        restoreInitialPreviewState();
    });

    return {
        schedulePreviewRefresh,
    };
}
