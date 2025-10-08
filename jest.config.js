module.exports = {
    globals: {
        "vue-jest": {
            hideStyleWarn: true,
            experimentalCSSCompile: true,
            compilerOptions: {
                isCustomElement: (tag) => tag.startsWith("sbx-"),
            },
        },
    },
    moduleFileExtensions: ["vue", "js", "ts"], // An array of file extensions your modules use. If you require modules without specifying a file extension, these are the extensions Jest will look for, in left-to-right order.
    moduleNameMapper: {
        // map any required webpack aliases across
        "^@schoolboxLearningMomentEvidence(.*)$": "<rootDir>/library/Schoolbox/Learning/Moment/vue$1",
        "^@schoolboxCurriculum(.*)$": "<rootDir>/library/Schoolbox/Curriculum/templates$1",
        "^@schoolboxBadge(.*)$": "<rootDir>/library/Schoolbox/Learning/Badge$1",
        "^@schoolboxAssessment(.*)$": "<rootDir>/library/Schoolbox/Assessment$1",
        "^@schoolboxVue(.*)$": "<rootDir>/vue$1",
        "^@js(.*)$": "<rootDir>/www/static/js$1",
        "^.+\\.(css|scss)$": "<rootDir>/jest-cssStub.js",
    },
    roots: ["<rootDir>/library/", "<rootDir>/templates/", "<rootDir>/vue/", "<rootDir>/www/"], // A list of paths to directories that Jest should use to search for files in.
    setupFilesAfterEnv: ["<rootDir>/jest-setup.js"], // file to run on every jest test
    snapshotSerializers: [
        // A list of paths to snapshot serializer modules Jest should use for snapshot testing.
        "<rootDir>/node_modules/jest-serializer-vue",
    ],
    testEnvironment: "@happy-dom/jest-environment",
    transform: {
        // A map from regular expressions to paths to transformers.
        "^.+\\.vue$": "@vue/vue3-jest",
        "^.+\\js$": "babel-jest",
        "^.+\\.tsx?$": [
            "ts-jest",
            {
                isolatedModules: true, // avoid type checking while running jest tests: https://kulshekhar.github.io/ts-jest/docs/getting-started/options/isolatedModules/
            },
        ],
    },
    transformIgnorePatterns: ["node_modules/(?!(gsap)/)"], // which files shall be transformed by Babel: https://jestjs.io/docs/tutorial-react-native#transformignorepatterns-customization
    verbose: true, // Indicates whether each individual test should be reported during the run. All errors will also still be shown on the bottom after execution. Note that if there is only one test file being run it will default to true.
};
