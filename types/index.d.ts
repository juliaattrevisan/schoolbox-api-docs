/**
 * Any global variables that we wont TS to recognise should be set up here:
 * https://ilikekillnerds.com/2022/09/how-to-extend-the-window-object-in-typescript/
 */

import Vue from "vue";

declare global {
    interface Window {
        cdnPrefix: string;
        CKEDITOR: any;
        sbProps: {
            lightGallery: Function;
            lightGalleryInit: Function;
            lightGalleries: { [key: string]: any };
            imageLightboxLicenseKey: string;
            imageSlideshows: { [key: string]: any };
            colourDefaults: { [key: string]: any };
        };
        siteVersion: string;
        STORYBOOK_ENV: string;
        Vue: typeof Vue;
    }
}

declare module "jquery" {
    export = $;
}

export {};
