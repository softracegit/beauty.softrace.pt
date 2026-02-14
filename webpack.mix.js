const mix = require('laravel-mix');
const rtlcss = require('rtlcss');
const fs = require('fs');

// Laravel paths
const folder = {
    src_assets: "resources/assets/",
    dist_assets: "public/assets/",
};

// Compile SCSS → CSS
mix.sass(folder.src_assets + "scss/app.scss", folder.dist_assets + "css/app.min.css")
   .sass(folder.src_assets + "scss/bootstrap.scss", folder.dist_assets + "css/bootstrap.min.css")
   .sass(folder.src_assets + "scss/icons.scss", folder.dist_assets + "css/icons.min.css");

// Copy static assets
mix.copyDirectory(folder.src_assets + "images", folder.dist_assets + "images");
mix.copyDirectory(folder.src_assets + "js", folder.dist_assets + "js");
mix.copyDirectory(folder.src_assets + "fonts", folder.dist_assets + "fonts");
mix.copyDirectory(folder.src_assets + "libs", folder.dist_assets + "libs");

// Enable BrowserSync (Laravel artisan serve)
mix.browserSync({
    proxy: "http://localhost:8000",
    files: [
        "app/**/*.php",
        "resources/views/**/*.php",
        "public/assets/**/*"
    ],
    open: false
});

// Minify automatically in production
if (mix.inProduction()) {
    mix.version();
}

// ✅ RTL CSS generation
mix.then(() => {
    const cssPairs = [
        { ltr: "public/assets/css/app.min.css", rtl: "public/assets/css/app-rtl.min.css" },
        { ltr: "public/assets/css/bootstrap.min.css", rtl: "public/assets/css/bootstrap-rtl.min.css" },
    ];

    cssPairs.forEach(file => {
        if (fs.existsSync(file.ltr)) {
            const ltrCss = fs.readFileSync(file.ltr, 'utf8');
            const rtlCss = rtlcss.process(ltrCss);
            fs.writeFileSync(file.rtl, rtlCss);
        }
    });
});
