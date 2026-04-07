/**
 * Any global variables that we want TS to recognise should be set up here:
 * https://ilikekillnerds.com/2022/09/how-to-extend-the-window-object-in-typescript/
 */

// Include type definitions from the @types/jquery package
/// <reference types="jquery" />

import type Vue from "vue";
import type dayjs from "dayjs";
import type { lightGallery } from "lightgallery";
import type { LightGallery } from "lightgallery/lightgallery";
import type { DefineComponent } from "vue";
import type { apiRequest } from "@js/util/sbx/apiUtils";
import type { InitLightGallery } from "@js/image-lightbox/imageLightbox";
import type { SbxEditor } from "@js/editor/types/editorTypes";
import type { RosettaStone } from "@js/terminology/rosettaStone";
import type { injectScriptsIfNotLoaded } from "@js/util/domUtils";
import type { MediaElementPlayer } from "@js/mediaelementplayer/mepUtils";
import type { SelectizeSelectorOptions } from "@js/vendors/selectize/selectizeSelectorTypes";
import type { AiFeature } from "@schoolboxLibrary/Admin/OpenAI/Usage/templates/payload";
import type { Toast } from "@schoolboxVue/components/toast/sbxToastTypes";
import type { ActivityType } from "@schoolboxAssessment/Types/assessmentTypes";
import type { SbxGlobalDocumentEventHandlerMap, SbxGlobalElementEventHandlerMap } from "./sbxEvents";
import type { chained } from "@js/chained/chained";

type Dropzone = {
    addFile(file: File): void;
    isValidFile(file: File, acceptedFiles: string): boolean;
};

declare global {
    const $: JQueryStatic;
    const jQuery: JQueryStatic;

    interface Window {
        cdnPrefix: string;
        dayjs: typeof dayjs;
        dateDisplayFormats: {
            calendarStartTime: string;
            dayjs: string;
            dayjsDatetime: string;
            dayjsDateLong: string;
            dayjsDateShort: string;
            dayjsTime: string;
            localDate: string;
            localDateTime: string;
            localTime: string;
        };
        iframely: {
            load: (containerElement: Element, url?: string) => void;
            on: (eventName: "cancel", callback: (url: string, parentNode: Element) => void) => void;
            on: (eventName: "click", callback: (href: string) => void) => void;
        };
        jQuery: JQueryStatic;
        Dropzone: Dropzone;
        MediaElementPlayer: MediaElementPlayer;
        sbProps: {
            apiRequest: typeof apiRequest;
            /** Enabled assessment types, mapped to default readable name, translated to match the current terminology */
            assessmentTypes: Record<
                Partial<ActivityType>,
                {
                    name: string;
                    icon: string;
                }
            >;
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
            gradeGradientEnabled: boolean;
            aiFeatures: Partial<{
                [K in AiFeature]: K extends AiFeature.CHAT // only some features have model info
                    ? { enabled: true; model: string } | { enabled: false }
                    : { enabled: boolean };
            }>;
            lightGallery: typeof lightGallery;
            lightGalleryInit: InitLightGallery;
            lightGalleries: Record<string, LightGallery | undefined>;
            imageLightboxLicenseKey: string;
            imageSlideshowListenerExists?: true | undefined;
            injectScriptsIfNotLoaded: typeof injectScriptsIfNotLoaded;
            toastActivate: Toast["activate"];
            MAX_BYTES_MYSQL_TEXT: number;
            MAX_BYTES_MYSQL_MEDIUMTEXT: number;
            l10n: RosettaStone;
            firebaseApps: {
                appIdentifier: string;
                publicConfiguration: Record<string, unknown>;
                webPushPublicKey: string;
            }[];
            // Curriculum nodes global storage
            storedNodes?: string;
            isStoringNodes?: boolean;
        };
        schoolboxUser: {
            id: number;
            externalId: string | null;
            name: string;
            impersonated: boolean;
            role: {
                id: number;
                name: string;
                guest: boolean;
                parent: boolean;
                staff: boolean;
                student: boolean;
                type: "staff" | "student" | "parent" | "guest";
            };
        };
        siteVersion: string;
        STORYBOOK_ENV: string;
        Vue: typeof Vue;
        sbx: {
            chained: typeof chained;
        };
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
        /**
         * From jquery.collapsable.js
         */
        collapsable(): JQuery;
        /**
         * From jpreview.ts - to preview media, opening in a lightbox
         */
        preview(): void;
        /**
         * From jquery-validation
         */
        valid(): boolean;
        /**
         * From jquery.combobox.js
         */
        combobox(selectOptions: string[]): JQuery;
        /**
         * From jquery.alaressValidate.js
         */
        alaressValidate(options?: Record<string, unknown>): JQuery & {
            destroy(): void;
            form(): boolean;
        };
        /**
         * From jquery.resourceAttach.js
         */
        data(key: "plugin_resourceAttach"): {
            dropzone: Dropzone;
            open: () => void;
        };
        /**
         * Reflow will make Foundation check the DOM for any elements and re-apply any listeners to them.
         * This is particularly useful to make tooltips work again if loading content in Vue after the initial page load
         */
        foundation(scope: "tooltip", method: "reflow"): void;
        /**
         * Open/close a Foundation reveal modal directly
         */
        foundation(scope: "reveal", method: "close" | "open" | { close_on_background_click?: boolean }): void;
        /**
         * From jquery.selectize-selector.js
         */
        selectizeSelector(options: SelectizeSelectorOptions): JQuery;
        selectizeSelector<TData, TValue>(options: SelectizeSelectorOptions<TData, TValue>): JQuery;
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
