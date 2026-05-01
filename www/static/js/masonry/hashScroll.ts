const MASONRY_LAYOUT_SELECTOR = "#component-layout";

function getCurrentHashTargetId(): string | null {
    const hash = window.location.hash;
    if (!hash) {
        return null;
    }

    return decodeURIComponent(hash.slice(1));
}

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

export function scrollToCurrentHashTargetOnLoad(): void {
    if (document.readyState === "complete") {
        scrollToCurrentHashTarget();
        return;
    }

    window.addEventListener("load", scrollToCurrentHashTarget, { once: true });
}
