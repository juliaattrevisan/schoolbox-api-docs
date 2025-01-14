/**
 * Any global variables that we want TS to recognise should be set up here:
 * https://ilikekillnerds.com/2022/09/how-to-extend-the-window-object-in-typescript/
 */

import type Vue from "vue";
import type dayjs from "dayjs";
import type { lightGallery } from "lightgallery";
import type { LightGallery } from "lightgallery/lightgallery";
import type { InitLightGallery } from "@js/image-lightbox/imageLightbox";
import type { DefineComponent } from "vue";
import type { SbxEditor } from "@js/editor/types/editorTypes";
import type { RosettaStone } from "@js/terminology/rosettaStone";
import type { injectScriptsIfNotLoaded } from "@js/util/domUtils";
import type { pwaConfig } from "@js/global/pwaConfig";
import type { SbxGlobalDocumentEventHandlerMap, SbxGlobalElementEventHandlerMap } from "./sbxEvents";

declare global {
    interface Window {
        cdnPrefix: string;
        dayjs: typeof dayjs;
        dateDisplayFormats: {
            dayjs: string;
        };
        iframely: {
            load: (containerElement: Element, url?: string) => void;
            on: (eventName: "cancel", callback: (url: string, parentNode: Element) => void) => void;
            on: (eventName: "click", callback: (href: string) => void) => void;
        };
        jQuery: JQueryStatic;
        sbProps: {
            ckeditor5LicenseKey: string;
            iframelyLicenseKey: string;
            siteTitle: string;
            colourDefaults: {
                skin: string[];
                skinUnique: string[];
                constant: string[];
                swatches: string[];
            };
            editors: Partial<Record<string, SbxEditor>>;
            hasEditorAI: true | undefined; // to display the AI features within the editor
            openAIModel: string | undefined; // chosen open AI model to use
            lightGallery: typeof lightGallery;
            lightGalleryInit: InitLightGallery;
            lightGalleries: Record<string, LightGallery | undefined>;
            imageLightboxLicenseKey: string;
            imageSlideshowListenerExists?: true | undefined;
            injectScriptsIfNotLoaded: typeof injectScriptsIfNotLoaded;
            MAX_BYTES_MYSQL_TEXT: number;
            MAX_BYTES_MYSQL_MEDIUMTEXT: number;
            l10n: RosettaStone;
            pwaConfig: typeof pwaConfig;
            firebaseApps: Array<{
                appIdentifier: string;
                publicConfiguration: { [key: string]: unknown };
                webPushPublicKey: string;
            }>;
            // Curriculum nodes global storage
            storedNodes?: string;
            isStoringNodes?: boolean;
        };
        schoolboxUser: {
            id: number;
            name: string;
            impersonated: boolean;
        };
        siteVersion: string;
        STORYBOOK_ENV: string;
        Vue: typeof Vue;
    }

    // Add our custom events to the DocumentEventMap, so we can use them in event listeners
    // DocumentEventMap is a built in event map that defines all events available to Document
    interface DocumentEventMap extends GlobalEventHandlersEventMap, SbxGlobalDocumentEventHandlerMap {}

    // Add our custom events to the HTMLElementEventMap, so we can use them in event listeners
    // HTMLElementEventMap is a built in event map that defines all events available to any HTML element
    interface HTMLElementEventMap extends GlobalEventHandlersEventMap, SbxGlobalElementEventHandlerMap {}

    interface JQuery {
        // we can add any extra methods we have extended onto the JQuery object here
        /**
         * Our custom datepicker component
         * www/static/js/alaress-datetime-picker/alaress-datetime-picker.js
         */
        alaressDateTimePicker(): JQuery;
        collapsable(): JQuery;
    }

    // Fix types when importing .vue files and using with window.Vue.createApp
    declare module "*.vue" {
        const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>;
        export default component;
    }

    declare module "*.svg" {
        const content: string;
        export default content;
    }
}
