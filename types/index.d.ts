/**
 * Any global variables that we wont TS to recognise should be set up here:
 * https://bobbyhadz.com/blog/typescript-extend-window
 */

declare global {
    let cdnPrefix: string;
    let siteVersion: string;
    let CKEDITOR: any;
    interface Window {
        STORYBOOK_ENV: string;
    }
}

declare module 'jquery' {
    export = $;
}

export {};
