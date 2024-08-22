/**
 * Here we are setting up our eslint configuration
 * https://eslint.org/docs/latest/user-guide/configuring/configuration-files#configuration-file-formats
 *
 * We are currently using the Airbnb config with TypeScript support, and also adding the additional checks typescript-eslint provides.
 * Prettier is added to the end so eslint doesnt report on formatting issues that prettier will automatically fix.
 */
module.exports = {
    // specifies the allowed environments: https://eslint.org/docs/latest/use/configure/language-options#specifying-environments
    env: {
        browser: true,
        commonjs: true,
        es6: true,
        jquery: true,
    },
    extends: [
        "airbnb-base",
        "airbnb-typescript/base",
        "plugin:@typescript-eslint/recommended",
        "plugin:@typescript-eslint/recommended-requiring-type-checking",
        "plugin:jsdoc/recommended",
        "plugin:vue/vue3-recommended",
        "prettier",
        "plugin:storybook/recommended",
    ],
    parser: "vue-eslint-parser",
    // to parse .vue files: https://eslint.vuejs.org/user-guide/#how-to-use-a-custom-parser
    parserOptions: {
        extraFileExtensions: [".vue"],
        parser: "@typescript-eslint/parser",
        // the parser that runs through our ts files
        project: "./tsconfig.json",
        tsconfigRootDir: __dirname,
    },
    plugins: [
        "import", // for eslint to lint imports
        "@typescript-eslint",
        // for eslint to be using typescript-eslint
        "eslint-plugin-jsdoc", // to lint jsdocs
    ],

    root: true,
    // limits ESLint to this folder (wont go up parent folders)
    rules: {
        // customised rules
        "import/prefer-default-export": "off",
        // as Pinia especially uses single exports
        "no-param-reassign": [
            2,
            {
                props: false,
            },
        ],
        // allow modifying props of parameters, especially valid when passing DOM elements, as we don"t really want to be cloning DOM elements
        "arrow-body-style": "off",
        // this can be at the dev discretion instead: https://eslint.org/docs/latest/rules/arrow-body-style
        "no-underscore-dangle": "off", // PHP will pass computed properties down with the _ identifier prepended: https://eslint.org/docs/latest/rules/no-underscore-dangle
        "no-void": "off", // void is a valid return type in TypeScript
        // allow devDependencies to be imported so that we can use them in our tests
        "import/no-extraneous-dependencies": [
            "error",
            {
                devDependencies: ["test/playwright/**/*.ts", "playwright.config.ts"],
            },
        ],
        // Allow console.warn and console.error, but disallow other console methods
        "no-console": [
            "error",
            {
                allow: ["warn", "error"],
            },
        ],
    },
    settings: {
        "import/parsers": {
            "@typescript-eslint/parser": [".ts", ".tsx"],
        },
        "import/resolver": {
            typescript: {
                alwaysTryTypes: true, // always try to resolve types under `<root>@types` directory even it doesn't contain any source code, like `@types/unist`
            },
        },
    },
};
