module.exports = {
    clearMocks: true,
    testMatch: ['<rootDir>/modules/**/*.test.js'],
    testPathIgnorePatterns: [
        '/node_modules/',
        '/modules/improved-notifications/libs/opentip/'
    ],
    watchman: true
};
