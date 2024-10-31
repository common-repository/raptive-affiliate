// webpack.mix.js

let mix = require('laravel-mix');

// mix.js('js/app.js', 'dist').setPublicPath('dist');

mix.sass('src/rapaf.scss', 'css').setPublicPath('dist');