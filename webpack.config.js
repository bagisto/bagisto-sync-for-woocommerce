/** @format */
/**
 * External dependencies
 */
const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');


const NODE_ENV = process.env.NODE_ENV || 'development';

const webpackConfig = {
    mode: NODE_ENV,
    watch: true,
    entry: {
        'admin': ['./src/js/admin/index.js', './src/scss/admin.scss']
    },
    devtool: NODE_ENV != 'production' ? 'inline-source-map' : false,
    output: {
        filename: 'js/[name].js',
        path: path.resolve(__dirname, 'assets'),
        libraryTarget: 'this',
    },
    module: {
        rules: [
            {
                test: /\.jsx?$/,
                loader: 'babel-loader',
                exclude: /node_modules/,
                parser: {
                    amd: false,
                },
            },
            {
                test: /\.js?$/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: [
                            [
                                '@babel/preset-env',
                                { loose: true, modules: 'commonjs' },
                            ],
                        ],
                        plugins: ['transform-es2015-template-literals'],
                    },
                },
                include: new RegExp(
                    '/node_modules/(' +
                    '|acorn-jsx' +
                    '|d3-array' +
                    '|debug' +
                    '|regexpu-core' +
                    '|unicode-match-property-ecmascript' +
                    '|unicode-match-property-value-ecmascript)/'
                ),
            },
            {
                test: /\.s?css$/,
                exclude: [/storybook\/wordpress/, /build-style\/*\/*.css/],
                use: [
                    MiniCssExtractPlugin.loader,
                    'css-loader',
                    {
                        loader: 'sass-loader',
                    },
                ],
            },
            {
                test: /\.json$/,
                type: 'json',
            },
        ],
    },
    resolve: {},
    plugins: [
        new MiniCssExtractPlugin({
            filename: 'css/[name].css',
        }),
        {
            apply: (compiler) => {
                if (webpackConfig.mode === 'production') {
                    compiler.hooks.done.tap('DonePlugin', (stats) => {
                        setTimeout(() => {
                            process.exit(0)
                        })
                    });
                }
            }
        }
    ],
    stats: 'errors-warnings',
};

if (webpackConfig.mode !== 'production') {
    webpackConfig.devtool = 'inline-source-map';
}
module.exports = webpackConfig;
