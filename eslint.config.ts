/**
 * Here we are setting up our eslint configuration
 * https://eslint.org/docs/latest/user-guide/configuring/configuration-files#configuration-file-formats
 *
 * We are currently using the Airbnb config with TypeScript support, and also adding the additional checks typescript-eslint provides.
 * Prettier is added to the end so eslint doesnt report on formatting issues that prettier will automatically fix.
 */

import { defineConfig, globalIgnores } from "eslint/config";
import globals from "globals";
import { jsdoc } from "eslint-plugin-jsdoc";
import { importX } from "eslint-plugin-import-x";
import { createTypeScriptImportResolver } from "eslint-import-resolver-typescript";
import eslintConfigPrettier from "eslint-config-prettier";
import eslintPluginVue from "eslint-plugin-vue";
import tsEslint from "typescript-eslint";
import vueEslintParser from "vue-eslint-parser";
import eslint from "@eslint/js";
import type { ConfigWithExtends } from "@eslint/config-helpers";
import eslintComments from "@eslint-community/eslint-plugin-eslint-comments/configs";

const mainConfig = defineConfig({
    languageOptions: {
        globals: {
            ...globals.browser,
            ...globals.commonjs,
            ...globals.jquery,
        },
        parser: vueEslintParser, // to parse .vue files: https://eslint.vuejs.org/user-guide/#how-to-use-a-custom-parser
        parserOptions: {
            parser: tsEslint.parser, // the parser that runs through our ts files
            projectService: true,
            tsconfigRootDir: __dirname,
            extraFileExtensions: [".vue"],
        },
    },
    // extends: compat.extends(
    // "airbnb-base",
    // "airbnb-typescript/base",
    // "plugin:storybook/recommended", // TODO in SA-21050, add this back in once storybook is updated
    // ),
    rules: {
        // customised rules
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
});

module.exports = defineConfig([
    eslint.configs.recommended,
    tsEslint.configs.recommendedTypeChecked,
    eslintComments.recommended,
    eslintPluginVue.configs["flat/recommended"],
    jsdoc({ config: "flat/recommended-typescript" }),
    // need to force these types for now, see https://github.com/un-ts/eslint-plugin-import-x/issues/439
    importX.flatConfigs.recommended as ConfigWithExtends,
    importX.flatConfigs.typescript as ConfigWithExtends,
    {
        rules: {
            // allow devDependencies to be imported so that we can use them in our tests
            "import-x/no-extraneous-dependencies": [
                "error",
                {
                    devDependencies: ["test/playwright/**/*.ts", "playwright.config.ts"],
                },
            ],
        },
        settings: {
            "import-x/parsers": {
                "@typescript-eslint/parser": [".ts", ".tsx"],
            },
            "import-x/resolver-next": [
                createTypeScriptImportResolver({
                    alwaysTryTypes: true, // always try to resolve types under `<root>@types` directory even it doesn't contain any source code, like `@types/unist`
                }),
            ],
        },
    },
    eslintConfigPrettier,
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
