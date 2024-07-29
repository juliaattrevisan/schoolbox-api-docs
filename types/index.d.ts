/**
 * Any global variables that we want TS to recognise should be set up here:
 * https://ilikekillnerds.com/2022/09/how-to-extend-the-window-object-in-typescript/
 */

import Vue from "vue";
import dayjs from "dayjs";
import type { DefineComponent } from "vue";
import type { Editor } from "@js/editor/types/editorTypes";
import type { RosettaStone } from "@js/terminology/rosettaStone";
import type { injectScriptsIfNotLoaded } from "@js/util/domUtils";
import type { pwaConfig } from "@js/global/pwaConfig";

declare global {
    interface Window {
        cdnPrefix: string;
        dayjs: typeof dayjs;
        dateDisplayFormats: {
            dayjs: string;
        };
        iframely: any;
        sbProps: {
            ckeditor5LicenseKey: string;
            iframelyLicenseKey: string;
            colourDefaults: { [key: string]: any };
            editors: { [key: string]: Editor };
            hasEditorAI: true | undefined; // to display the AI features within the editor
            openAIModel: string | undefined; // chosen open AI model to use
            lightGallery: Function;
            lightGalleryInit: Function;
            lightGalleries: { [key: string]: any };
            imageLightboxLicenseKey: string;
            imageSlideshows: { [key: string]: any };
            injectScriptsIfNotLoaded: typeof injectScriptsIfNotLoaded;
            MAX_BYTES_MYSQL_TEXT: number;
            MAX_BYTES_MYSQL_MEDIUMTEXT: number;
            l10n: RosettaStone;
            pwaConfig: typeof pwaConfig;
            firebaseApps: Array<{
                appIdentifier: string;
                publicConfiguration: { [key: string]: any };
                webPushPublicKey: string;
            }>;
        };
        schoolboxUser: {
            id: number;
            name: string;
        };
        siteVersion: string;
        STORYBOOK_ENV: string;
        Vue: typeof Vue;
    }

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
}
