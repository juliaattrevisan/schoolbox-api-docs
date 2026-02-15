/**
 * Storybook configuration for Vue 3 using Rsbuild.
 * This file sets up Storybook to work with Vue 3 components,
 * including SCSS support and custom path aliases.
 */

import { pluginVue } from "@rsbuild/plugin-vue";
import { pluginSass } from '@rsbuild/plugin-sass';
import { resolve, dirname } from "path";
import { fileURLToPath } from "url";
import remarkGfm from "remark-gfm";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const config = {
    stories: ["../stories/**/*.mdx", "../stories/**/*.stories.@(js|ts)"],
    staticDirs: ["../stories/assets"],
    addons: [
        {
            name: "@storybook/addon-docs",
            options: {
                mdxPluginOptions: {
                    mdxCompileOptions: {
                        remarkPlugins: [remarkGfm],
                    },
                },
            },
        },
    ],
    framework: "storybook-vue3-rsbuild",
    rsbuildFinal: async (config) => {
        /**
         * Add in the required aliases
         */
        config.resolve.alias["@js"] = resolve(__dirname, "../www/static/js");
        config.resolve.alias["@schoolboxVue"] = resolve(__dirname, "../vue");
        config.resolve.alias["@schoolboxBadge"] = resolve(__dirname, "../library/Schoolbox/Learning/Badge/");
        config.resolve.alias["@schoolboxLibrary"] = resolve(__dirname, "../library/Schoolbox");
        config.resolve.alias["@schoolboxCurriculum"] = resolve(__dirname, "../library/Schoolbox/Curriculum/templates/");
        config.resolve.alias["@css"] = resolve(__dirname, "../www/static/css");
        config.resolve.alias["@library"] = resolve(__dirname, "../library");
        /**
         * Add in the plugins to compile Vue files and handle SCSS.
         */
        config.plugins = config.plugins || [];
        config.plugins.push(pluginVue(), pluginSass());
        return config;
    },
};
export default config;
