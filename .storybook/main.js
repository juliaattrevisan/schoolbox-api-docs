const path = require('path');

module.exports = {
    addons: [
        '@storybook/addon-a11y',
        '@storybook/addon-actions',
        '@storybook/addon-essentials',
        '@storybook/addon-interactions',
        '@storybook/addon-links',
        '@storybook/addon-storysource',
    ],
    core: {
        builder: 'webpack5',
    },
    features: {
        postcss: false,
    },
    framework: '@storybook/vue3',
    staticDirs: ['../stories/assets'],
    stories: [
        '../stories/**/*.stories.mdx',
        '../stories/**/*.stories.@(js|jsx|ts|tsx)',
        '../vue/components/**/*.stories.@(js|jsx|ts|tsx)',
    ],
    webpackFinal: async (config, { configType }) => {
        // `configType` has a value of 'DEVELOPMENT' or 'PRODUCTION'
        // You can change the configuration based on that.
        // 'PRODUCTION' is used when building the static version of storybook.

        // Make whatever fine-grained changes you need
        config.module.rules.push({
            test: /\.scss$/,
            use: ['style-loader', 'css-loader', 'sass-loader'],
            include: path.resolve(__dirname, '../'),
        });

        config.resolve.alias['@js'] = path.resolve(__dirname, '../www/static/js');
        config.resolve.alias['@schoolboxVue'] = path.resolve(__dirname, '../vue');
        config.resolve.alias['@css'] = path.resolve(__dirname, '../www/static/css');
        config.resolve.alias['@library'] = path.resolve(__dirname, '../library');

        // Return the altered config
        return config;
      },
}
