/**
 * Any global variables that we wont TS to recognise should be set up here:
 * https://bobbyhadz.com/blog/typescript-extend-window
 */

export {};

declare global {
    interface Window {
        STORYBOOK_ENV: string;
    }
}
