/**
 * Composable for requesting and applying the live Skin Config preview stylesheet.
 */

import type { Ref } from "vue";
import { debounce } from "es-toolkit";
import type { SkinConfigData } from "../../js/types/skinConfigTypes";

const PREVIEW_DEBOUNCE_MS = 500;
const PREVIEW_STYLE_ID = "skin-config-preview-style";

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
     * Remove the injected preview stylesheet.
     */
    function removePreviewStyle(): void {
        const existingElement = document.getElementById(PREVIEW_STYLE_ID);
        if (existingElement instanceof HTMLStyleElement) {
            existingElement.remove();
        }
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

    window.Vue.onBeforeUnmount(() => {
        cancelPendingPreview();
        removePreviewStyle();
    });

    return {
        schedulePreviewRefresh,
    };
}
