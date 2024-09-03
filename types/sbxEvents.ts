/**
 * When using document.addEventListener to listen for custom events, TypeScript doesn't know what the content of
 * the event will be, often leading to "as CustomEvent<SomeType>" being used to cast the event to the correct type.
 *
 * To avoid this, we can extend the DocumentEventMap interface to include our custom events, which tells TypeScript
 * what the event object is for any given named event
 */
import type { EditorDocumentEventHandlerMap, EditorElementEventHandlerMap } from "@js/editor/event/editorEvent";
import type { ColourPickerDocumentEventHandlerMap } from "@js/colour-picker/colourPickerEvent";
import type { PopOutMenuMarkbookDocumentEventHandlerMap } from "@schoolboxVue/components/menu/popOutMenuMarkbookEvent";
import type { ImageLightboxDocumentEventHandlerMap } from "@js/image-lightbox/imageLightboxEvent";
import type { SwiperDocumentEventHandlerMap } from "@js/slideshow/slideshowEvent";
import type { FirebaseDocumentEventHandlerMap } from "@js/firebase/firebaseEvent";

/**
 * FOR EVENTS DISPATCHED TO THE DOCUMENT
 * The event map to link all custom event names to their event object types
 * You can either extend an existing event map interface, or just add the custom events directly
 */
export interface SbxGlobalDocumentEventHandlerMap
    extends EditorDocumentEventHandlerMap,
        ColourPickerDocumentEventHandlerMap,
        ImageLightboxDocumentEventHandlerMap,
        SwiperDocumentEventHandlerMap,
        PopOutMenuMarkbookDocumentEventHandlerMap,
        FirebaseDocumentEventHandlerMap {}

/**
 * FOR EVENTS DISPATCHED TO ANY ELEMENT
 */
export interface SbxGlobalElementEventHandlerMap extends EditorElementEventHandlerMap {}
