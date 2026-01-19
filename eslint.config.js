/**
 * Here we are setting up our eslint configuration
 * https://eslint.org/docs/latest/user-guide/configuring/configuration-files#configuration-file-formats
 *
 * We are currently using the Airbnb config with TypeScript support, and also adding the additional checks typescript-eslint provides.
 * Prettier is added to the end so eslint doesnt report on formatting issues that prettier will automatically fix.
 */

const { defineConfig, globalIgnores } = require("eslint/config");
const globals = require("globals");
const vueEslintParser = require("vue-eslint-parser");
const typescriptEslint = require("@typescript-eslint/eslint-plugin");
const jsdoc = require("eslint-plugin-jsdoc");
const eslintComments = require("@eslint-community/eslint-plugin-eslint-comments/configs");
const js = require("@eslint/js");

const { FlatCompat } = require("@eslint/eslintrc");

const compat = new FlatCompat({
    baseDirectory: __dirname,
    recommendedConfig: js.configs.recommended,
    allConfig: js.configs.all,
});

const mainConfig = defineConfig({
    languageOptions: {
        globals: {
            ...globals.browser,
            ...globals.commonjs,
            ...globals.jquery,
        },
        parser: vueEslintParser, // to parse .vue files: https://eslint.vuejs.org/user-guide/#how-to-use-a-custom-parser
        parserOptions: {
            extraFileExtensions: [".vue"],
            parser: "@typescript-eslint/parser", // the parser that runs through our ts files
            project: "./tsconfig.json",
            tsconfigRootDir: __dirname,
        },
    },
    extends: compat.extends(
        // "airbnb-base",
        // "airbnb-typescript/base",
        "plugin:@typescript-eslint/recommended",
        "plugin:@typescript-eslint/recommended-requiring-type-checking",
        "plugin:jsdoc/recommended",
        "plugin:vue/vue3-recommended",
        "plugin:import-x/recommended",
        "plugin:import-x/typescript",
        "prettier",
        // "plugin:storybook/recommended", // TODO in SA-21050, add this back in once storybook is updated
    ),
    plugins: {
        "@typescript-eslint": typescriptEslint,
        jsdoc,
    },
    rules: {
        // customised rules
        "import-x/prefer-default-export": "off",
        // as Pinia especially uses single exports
        "no-param-reassign": [
            2,
            {
                props: false,
            },
        ],
        // allow modifying props of parameters, especially valid when passing DOM elements, as we don"t really want to be cloning DOM elements
        // this can be at the dev discretion instead: https://eslint.org/docs/latest/rules/arrow-body-style
        "arrow-body-style": "off",
        "no-underscore-dangle": "off", // PHP will pass computed properties down with the _ identifier prepended: https://eslint.org/docs/latest/rules/no-underscore-dangle
        "no-void": "off", // void is a valid return type in TypeScript
        // allow devDependencies to be imported so that we can use them in our tests
        "import-x/no-extraneous-dependencies": [
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
        // enforce consistent explicit return types on functions and class methods
        "@typescript-eslint/explicit-function-return-type": "warn",
        "@typescript-eslint/typedef": [
            "warn",
            {
                parameter: true, // enforce parameters of functions to have explicit types
                propertyDeclaration: true, // enforce properties of interfaces/types to have explicit types
            },
        ],
        // due to the above rules enforcing explicit types, we prevent types from being included in the JSDoc
        "jsdoc/require-param-type": "off",
        "jsdoc/require-returns-type": "off",
        "jsdoc/no-types": "warn",
        // change no-unused-vars to warn instead of error (to make it less misleading)
        "@typescript-eslint/no-unused-vars": "warn",
        // enforce "import type" for type only imports
        "@typescript-eslint/consistent-type-imports": [
            "warn",
            {
                prefer: "type-imports",
            },
        ],
        // avoid unnecessary eslint-disable comments and enforce descriptions for them
        "@eslint-community/eslint-comments/no-unused-disable": "error",
        "@eslint-community/eslint-comments/require-description": "warn",
        /**
         * Override the ForOfStatement restriction from airbnb, as we want to allow
         * for-of loops (including with await) as they are more readable than .forEach or .reduce
         * and our browser targets support them without the need for regenerator-runtime
         * It should also help avoid Promise.all() accidentally being used for operations that must be sequential
         */
        "no-restricted-syntax": ["error", "ForInStatement", "LabeledStatement", "WithStatement"],
        "no-await-in-loop": "off",
    },
    settings: {
        "import-x/parsers": {
            "@typescript-eslint/parser": [".ts", ".tsx"],
        },
        "import-x/resolver": {
            typescript: {
                alwaysTryTypes: true, // always try to resolve types under `<root>@types` directory even it doesn't contain any source code, like `@types/unist`
            },
        },
    },
});

module.exports = defineConfig([
    eslintComments.recommended,
    mainConfig,
    globalIgnores([
        "**/*.js",
        "**/.git",
        "**/.vagrant",
        "**/conf",
        "**/debian",
        "**/docs",
        "**/debian",
        "**/node_modules",
        "**/packages",
        "**/patches",
        "**/sql",
        "**/tools",
        "**/updates",
        "**/vagrantcache",
        "**/vendor",
        "www/static/js/bundle",
    ]),
]);
