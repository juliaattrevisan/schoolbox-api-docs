module.exports = {
    moduleFileExtensions: ['vue', 'js', 'ts'], // An array of file extensions your modules use. If you require modules without specifying a file extension, these are the extensions Jest will look for, in left-to-right order.
    roots: ['<rootDir>/library/', '<rootDir>/templates/', '<rootDir>/vue/'], // A list of paths to directories that Jest should use to search for files in.
    snapshotSerializers: [ // A list of paths to snapshot serializer modules Jest should use for snapshot testing.
        '<rootDir>/node_modules/jest-serializer-vue'
    ],
    transform: { // A map from regular expressions to paths to transformers.
        '^.+\\.vue$': 'vue-jest',
        '^.+\\js$': 'babel-jest'
    },
    moduleNameMapper: { // map any required webpack aliases across
        '^@schoolboxCurriculum(.*)$': '<rootDir>/library/Schoolbox/Curriculum/templates$1',
        '^@schoolboxVue(.*)$': '<rootDir>/vue$1',
        '^@js(.*)$': '<rootDir>/www/static/js$1',
    },
    transformIgnorePatterns: ['node_modules/(?!(gsap)/)'], // which files shall be transformed by Babel: https://jestjs.io/docs/tutorial-react-native#transformignorepatterns-customization
    verbose: true, // Indicates whether each individual test should be reported during the run. All errors will also still be shown on the bottom after execution. Note that if there is only one test file being run it will default to true.
}