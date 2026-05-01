const MASONRY_LAYOUT_SELECTOR = "#component-layout";

/**
 * Get the current location hash without the leading #.
 * @returns The decoded hash target id, or null when no hash is present.
 */
function getCurrentHashTargetId(): string | null {
    const { hash } = window.location;
    if (!hash) {
        return null;
    }

    return decodeURIComponent(hash.slice(1));
}

/**
 * Scroll to the current hash target when it is inside the masonry layout.
 */
export function scrollToCurrentHashTarget(): void {
    const targetId = getCurrentHashTargetId();
    if (!targetId) {
        return;
    }

    const layout = document.querySelector(MASONRY_LAYOUT_SELECTOR);
    const target = document.getElementById(targetId);
    if (!layout || !target || !layout.contains(target)) {
        return;
    }

    target.scrollIntoView({ block: "start" });
}

/**
 * Scroll to the current hash target once all page assets have loaded.
 */
export function scrollToCurrentHashTargetOnLoad(): void {
    if (document.readyState === "complete") {
        scrollToCurrentHashTarget();
        return;
    }

    window.addEventListener("load", scrollToCurrentHashTarget, { once: true });
}
