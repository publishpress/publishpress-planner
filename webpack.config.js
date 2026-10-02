const NODE_ENV = process.env.NODE_ENV || 'development';
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = {
    mode: NODE_ENV,
    entry: {
        'modules/calendar/lib/async-calendar/js/index': './modules/calendar/lib/async-calendar/js/index.jsx',
        'modules/efmigration/lib/js/efmigration': './modules/efmigration/lib/js/efmigration.jsx',
        'modules/calendar/lib/async-calendar/styles/async-calendar': './modules/calendar/lib/async-calendar/styles/async-calendar.less',
        'modules/calendar/lib/async-calendar/styles/themes/theme-light': './modules/calendar/lib/async-calendar/styles/themes/theme-light.less'
    },
    output: {
        path: __dirname,
        filename: '[name].min.js'
    },
    module: {
        rules: [
            {
                test: /\.jsx?$/,
                exclude: /node_modules/,
                loader: 'babel-loader'
            },
            {
                test: /\.js$/,
                enforce: 'pre',
                use: ['source-map-loader'],
            },
            {
                test: /\.less$/,
                use: [
                    MiniCssExtractPlugin.loader,
                    { loader: 'css-loader', options: { sourceMap: true } },
                    { loader: 'less-loader', options: { sourceMap: true } },
                ],
            },
        ]
    },
    resolve: {
        extensions: ['.js', '.jsx']
    },
    plugins: [
        new MiniCssExtractPlugin({ filename: '[name].css' }),
    ],
    externals: {
        "&wp.element": "wp.element",
        "&ReactDOM": "ReactDOM",
        "@wordpress/i18n": "wp.i18n"
    }
};
