/**
 * Tweak the dark theme so it's more in line with the Schoolbox brand
 * https://storybook.js.org/docs/configure/user-interface/theming#create-a-theme-quickstart
 */

import { create } from "@storybook/theming/create";

export default create({
    base: "dark",
    brandTitle: "sbx design system",
    brandUrl: "https://schoolbox.education",
    brandImage: "/sb-logo-white.png",
    brandTarget: "_self",
    appBg: "#140021",
    appContentBg: "#140021",
    appPreviewBg: "#140021",
    barBg: "#140021",
});
