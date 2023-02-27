/**
 * Any global variables that we wont TS to recognise should be set up here:
 * https://ilikekillnerds.com/2022/09/how-to-extend-the-window-object-in-typescript/
 */

export {};

declare global {
    interface Window {
        cdnPrefix: string;
        siteVersion: string;
        CKEDITOR: any;
        STORYBOOK_ENV: string;
        sbProps: {
            lightGallery: Function;
        };
    }
}

declare module "jquery" {
    export = $;
}

export {};
