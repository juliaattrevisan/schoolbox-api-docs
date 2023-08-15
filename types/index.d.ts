/**
 * Any global variables that we want TS to recognise should be set up here:
 * https://ilikekillnerds.com/2022/09/how-to-extend-the-window-object-in-typescript/
 */

import Vue from "vue";
import dayjs from "dayjs";
import { ActiveDropdown } from "@js/dropdown/dropdownTypes";

declare global {
    interface Window {
        $: JQuery;
        cdnPrefix: string;
        CKEDITOR: any;
        dayjs: typeof dayjs;
        sbProps: {
            lightGallery: Function;
            lightGalleryInit: Function;
            lightGalleries: { [key: string]: any };
            imageLightboxLicenseKey: string;
            imageSlideshows: { [key: string]: any };
            colourDefaults: { [key: string]: any };
            activeDropdown: ActiveDropdown | false;
        };
        siteVersion: string;
        STORYBOOK_ENV: string;
        Vue: typeof Vue;
    }
    interface JQuery {
        // we can add any extra methods we have extended onto the JQuery object here
        alaressDateTimePicker(): JQuery;
    }
}
