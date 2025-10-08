/**
 * Events dispatched from library/Schoolbox/Filter/templates/panel.twig
 * Includes a key to identify where it came from, assuming that place actually specified a key
 */
export enum FilterEvent {
    FILTER__CONTENT_LOADED = "FILTER__CONTENT_LOADED",
    FILTER__CONTENT_LOADING = "FILTER__CONTENT_LOADING",
}

type FilterEventKey = string | null;

/** I haven't properly typed this as we don't use it anywhere in TS yet, feel free to add types if you use it */
type FilterDecodedSelections = unknown;

export type FilterEventContentLoadedInfo<T = unknown> = {
    key: FilterEventKey;
    content: T;
    decodedSelections: FilterDecodedSelections;
};

export type FilterEventContentLoadingInfo = {
    key: FilterEventKey;
    decodedSelections: FilterDecodedSelections;
};

export interface FilterEventHandlerMap {
    [FilterEvent.FILTER__CONTENT_LOADED]: CustomEvent<FilterEventContentLoadedInfo>;
    [FilterEvent.FILTER__CONTENT_LOADING]: CustomEvent<FilterEventContentLoadingInfo>;
}

/**
 * Listen for a when a filter finishes loading its content successfully
 * @param key - The key of the filter that loaded
 * @param callback - The function to call when the event is dispatched
 * @template T - The type of the content expected to be loaded, defaults to unknown
 */
export function listenForFilterLoad<T = unknown>(
    key: FilterEventKey,
    callback: (detail: FilterEventContentLoadedInfo<T>) => void,
): void {
    document.addEventListener(FilterEvent.FILTER__CONTENT_LOADED, (event) => {
        if (event.detail.key === key) {
            callback(event.detail as FilterEventContentLoadedInfo<T>);
        }
    });
}

/**
 * Listen for a when a filter starts loading its content
 * @param key - The key of the filter that is loading
 * @param callback - The function to call when the event is dispatched
 */
export function listenForFilterLoading(
    key: FilterEventKey,
    callback: (detail: FilterEventContentLoadingInfo) => void,
): void {
    document.addEventListener(FilterEvent.FILTER__CONTENT_LOADING, (event) => {
        if (event.detail.key === key) {
            callback(event.detail);
        }
    });
}
