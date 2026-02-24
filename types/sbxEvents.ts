/**
 * When using document.addEventListener to listen for custom events, TypeScript doesn't know what the content of
 * the event will be, often leading to "as CustomEvent<SomeType>" being used to cast the event to the correct type.
 *
 * To avoid this, we can extend the DocumentEventMap interface to include our custom events, which tells TypeScript
 * what the event object is for any given named event
 */
import type { EditorDocumentEventHandlerMap, EditorElementEventHandlerMap } from "@js/editor/event/editorEvent";
import type { ColourPickerDocumentEventHandlerMap } from "@js/colour-picker/colourPickerEvent";
import type { ImageLightboxDocumentEventHandlerMap } from "@js/image-lightbox/imageLightboxEvent";
import type { SwiperDocumentEventHandlerMap } from "@js/slideshow/slideshowEvent";
import type { ActivityStatusPageDocumentEventHandlerMap } from "@schoolboxLibrary/Learning/Assessment/templates/status/activityStatusPageEvent";
import type { FiltersChangeEventHandlerMap } from "@schoolboxVue/components/grid/lazyGrid/event/LazyGridEvent";
import type { FilterEventHandlerMap } from "@schoolboxLibrary/Filter/templates/ts/filterEvent";

/**
 * FOR EVENTS DISPATCHED TO THE DOCUMENT
 * The event map to link all custom event names to their event object types
 * You can either extend an existing event map interface, or just add the custom events directly
 */
export interface SbxGlobalDocumentEventHandlerMap
    extends
        EditorDocumentEventHandlerMap,
        ColourPickerDocumentEventHandlerMap,
        ImageLightboxDocumentEventHandlerMap,
        SwiperDocumentEventHandlerMap,
        ActivityStatusPageDocumentEventHandlerMap,
        FiltersChangeEventHandlerMap,
        FilterEventHandlerMap {}

/**
 * FOR EVENTS DISPATCHED TO ANY ELEMENT
 * For now, this is identical to SbxGlobalDocumentEventHandlerMap
 */
export type SbxGlobalElementEventHandlerMap = EditorElementEventHandlerMap;
