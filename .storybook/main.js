/**
 * Storybook configuration for Vue 3 using Rsbuild.
 * This file sets up Storybook to work with Vue 3 components, including SCSS support and custom path aliases.
 */

import { pluginVue } from "@rsbuild/plugin-vue";
import { resolve, dirname } from "path";
import { fileURLToPath } from "url";
import remarkGfm from "remark-gfm";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

/** @type { import('storybook-vue3-rsbuild').StorybookConfig } */
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
         * Configure SCSS processing via Rsbuild's tools.rspack
         * Rsbuild has built-in SCSS support when sass is installed,
         * but we configure it explicitly here for custom loader options.
         */
        config.tools = config.tools || {};
        config.tools.rspack = config.tools.rspack || {};
        config.tools.rspack.module = config.tools.rspack.module || {};
        config.tools.rspack.module.rules = config.tools.rspack.module.rules || [];
        config.tools.rspack.module.rules.push({
            test: /\.scss$/,
            use: [
                "style-loader",
                {
                    loader: "css-loader",
                    options: {
                        url: false, // don't validate image urls as they error
                    },
                },
                "sass-loader",
            ],
            include: resolve(__dirname, "../"),
        });
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
         * Add in the plugin to compile Vue files.
         */
        config.plugins = config.plugins || [];
        config.plugins.push(pluginVue());
        return config;
    },
};
export default config;
