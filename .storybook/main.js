import { pluginVue } from "@rsbuild/plugin-vue";

/** @type { import('storybook-vue3-rsbuild').StorybookConfig } */
const config = {
    stories: ["../stories/**/*.mdx", "../stories/**/*.stories.@(js|ts)"],
    staticDirs: ["../stories/assets"],
    addons: ["@storybook/addon-docs"],
    framework: "storybook-vue3-rsbuild",
    rsbuildFinal: async (config) => {
        config.plugins = config.plugins || [];
        config.plugins.push(pluginVue());
        return config;
    },
};
export default config;
