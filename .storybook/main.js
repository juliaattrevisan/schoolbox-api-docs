const path = require("path");
module.exports = {
    addons: [
        "@storybook/addon-a11y",
        "@storybook/addon-actions",
        "@storybook/addon-essentials",
        "@storybook/addon-interactions",
        "@storybook/addon-links",
        "@storybook/addon-storysource",
        "@storybook/addon-mdx-gfm",
        "@storybook/addon-webpack5-compiler-swc",
    ],
    features: {
        postcss: false,
    },
    framework: {
        name: "@storybook/vue3-webpack5",
        options: { builder: {} },
    },
    staticDirs: ["../stories/assets"],
    stories: [
        "../stories/**/*.mdx",
        "../stories/**/*.stories.@(js|ts)",
        "../vue/components/**/*.mdx",
        "../vue/components/**/*.stories.@(js|ts)",
    ],
    webpackFinal: async (config, { configType }) => {
        // Add in the required matched rules from webpack.common.js
        config.module.rules.push({
            test: /\.scss$/,
            use: ["style-loader", "css-loader", "sass-loader"],
            include: path.resolve(__dirname, "../"),
        });
        config.resolve.alias["@js"] = path.resolve(__dirname, "../www/static/js");
        config.resolve.alias["@schoolboxVue"] = path.resolve(__dirname, "../vue");
        config.resolve.alias["@schoolboxCurriculum"] = path.resolve(
            __dirname,
            "../library/Schoolbox/Curriculum/templates/",
        );
        config.resolve.alias["@css"] = path.resolve(__dirname, "../www/static/css");
        config.resolve.alias["@library"] = path.resolve(__dirname, "../library");

        // Return the altered config
        return config;
    },
    docs: {},
};
