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
import { rules as eslintConfigAirbnb } from "eslint-config-airbnb-extended";
import eslintConfigPrettier from "eslint-config-prettier";
import eslintPluginVue from "eslint-plugin-vue";
import tsEslint from "typescript-eslint";
import vueEslintParser from "vue-eslint-parser";
import eslint from "@eslint/js";
import type { ConfigWithExtends } from "@eslint/config-helpers";
import eslintComments from "@eslint-community/eslint-plugin-eslint-comments/configs";
import eslintPluginStylistic from "@stylistic/eslint-plugin";

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
    // "plugin:storybook/recommended", // TODO in SA-21050, add this back in once storybook is updated
    // ),
    rules: {
        // customised rules
        // allow modifying props of parameters, especially valid when passing DOM elements, as we don"t really want to be cloning DOM elements
        "no-param-reassign": ["warn", { props: false }],
        "no-underscore-dangle": "off", // PHP will pass computed properties down with the _ identifier prepended: https://eslint.org/docs/latest/rules/no-underscore-dangle
        "no-void": "off", // void is a valid return type in TypeScript
        // Allow console.warn and console.error, but disallow other console methods
        "no-console": ["warn", { allow: ["warn", "error", "info"] }],
        // enforce consistent explicit return types on functions and class methods (except for expressions)
        "@typescript-eslint/explicit-function-return-type": ["warn", { allowExpressions: true }],
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
        "@typescript-eslint/consistent-type-imports": ["warn", { prefer: "type-imports" }],
        // avoid unnecessary eslint-disable comments and enforce descriptions for them
        "@eslint-community/eslint-comments/no-unused-disable": "warn",
        "@eslint-community/eslint-comments/require-description": "warn",
    },
});

module.exports = defineConfig([
    eslint.configs.recommended,
    eslintConfigAirbnb.base.bestPractices,
    eslintConfigAirbnb.base.errors,
    eslintConfigAirbnb.base.es6,
    eslintConfigAirbnb.base.style,
    eslintConfigAirbnb.base.stylistic,
    eslintConfigAirbnb.typescript.base,
    eslintConfigAirbnb.typescript.typescriptEslint,
    {
        rules: {
            /* Selective rules from eslintConfigAirbnb.base.variables */
            "no-restricted-globals": eslintConfigAirbnb.base.variables.rules!["no-restricted-globals"],
            /**
             * Override the ForOfStatement restriction from airbnb, as we want to allow
             * for-of loops (including with await) as they are more readable than .forEach or .reduce
             * and our browser targets support them without the need for regenerator-runtime
             * It should also help avoid Promise.all() accidentally being used for operations that must be sequential
             */
            "no-restricted-syntax": ["error", "ForInStatement", "LabeledStatement", "WithStatement"],

            /* other overrides from rules that come from the airbnb configs */
            "arrow-body-style": "off",
            "no-await-in-loop": "off",
            "no-underscore-dangle": "off",
            "@stylistic/lines-between-class-members": "off",
            "@typescript-eslint/no-inferrable-types": "off",
            "@typescript-eslint/consistent-type-definitions": "off",
        },
    },
    tsEslint.configs.recommendedTypeChecked,
    eslintPluginVue.configs["flat/recommended"],
    eslintComments.recommended,
    {
        plugins: { "@stylistic": eslintPluginStylistic },
        rules: { "@stylistic/spaced-comment": "warn" },
    },
    jsdoc({
        files: ["**/*.js", "**/*.mjs"],
        config: "flat/recommended",
    }),
    jsdoc({
        files: ["**/*.ts", "**/*.vue"],
        config: "flat/recommended-typescript",
    }),
    // need to force these types for now, see https://github.com/un-ts/eslint-plugin-import-x/issues/439
    importX.flatConfigs.recommended as ConfigWithExtends,
    importX.flatConfigs.typescript as ConfigWithExtends,
    {
        rules: {
            // allow devDependencies to be imported so that we can use them in our tests
            "import-x/no-extraneous-dependencies": [
                "error",
                {
                    devDependencies: [
                        "test/playwright/**/*.ts",
                        "playwright.config.ts",
                        "eslint.config.ts",
                        "stories/**/*.stories.ts",
                    ],
                },
            ],
            "import-x/first": "warn",
            "import-x/newline-after-import": "warn",
            "import-x/no-absolute-path": "warn",
            "import-x/no-amd": "warn",
            "import-x/no-mutable-exports": "error",
            "import-x/no-named-default": "error",
            "import-x/no-relative-packages": "error",
            "import-x/no-self-import": "error",
            "import-x/no-cycle": ["error", { maxDepth: "∞" }],
            // Disable some rules for performance as per recommendations in https://typescript-eslint.io/troubleshooting/typed-linting/performance/#eslint-plugin-import
            "import-x/named": "off",
            "import-x/namespace": "off",
            "import-x/no-named-as-default-member": "off",
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
        "www/static/js/pdf/pdfjs",
    ]),
]);
