/**
 * Any global variables that we want TS to recognise should be set up here:
 * https://ilikekillnerds.com/2022/09/how-to-extend-the-window-object-in-typescript/
 */

import Vue from "vue";
import dayjs from "dayjs";
import type { Editor } from "@ckeditor/ckeditor5-core";

declare global {
    interface Window {
        cdnPrefix: string;
        CKEDITOR: any;
        dayjs: typeof dayjs;
        dateDisplayFormats: {
            dayjs: string;
        };
        sbProps: {
            lightGallery: Function;
            lightGalleryInit: Function;
            lightGalleries: { [key: string]: any };
            imageLightboxLicenseKey: string;
            ckeditor5LicenseKey: string;
            imageSlideshows: { [key: string]: any };
            colourDefaults: { [key: string]: any };
            MAX_BYTES_MYSQL_TEXT: number;
            MAX_BYTES_MYSQL_MEDIUMTEXT: number;
            editors: { [key: string]: Editor };
            hasEditorAI: boolean;
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
}
