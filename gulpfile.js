/*
 * This is default barebone for gulp, add your own modifications here
 * It also serves as an example of how to use browser-sync in this environment
 */
var gulp        = require('gulp');
var browserSync = require('browser-sync');
var sass        = require('gulp-sass');
var reload      = browserSync.reload;

/*
 * src array contains the files which the gulp will be watching
 * Choose your theme over here (twentyfifteen is provided as an example)
 */
var src = {
    scss: 'htdocs/wp-content/themes/twentyfifteen/scss/*.scss',
    css:  'htdocs/wp-content/themes/twentyfifteen/css/',
    php: [
        'htdocs/wp-content/themes/*/*.php',
        'htdocs/wp-content/plugins/*/*.php',
        'htdocs/wp-content/mu-plugins/*/*.php'
    ]
};

// Serve all files through browser-sync
gulp.task('serve', ['sass'], function() {

    // Initialize browsersync
    // Nginx is configured to use any service in port 1337
    // as middleware to WordPress in vagrant environment
    browserSync.init({
        // browsersync with a php server
        proxy: "http://localhost:8080",
        port: 1337,
        ui: {
            port: 1338
        },
        notify: true
    });

    // Watch sass files and compile them
    gulp.watch(src.scss, ['sass']);

    // Update the page automatically if php files change
    gulp.watch(src.php).on('change', reload);
});

// Give another name for serve
gulp.task('watch',['serve'], function() {
});

// Compile sass into CSS
gulp.task('sass', function() {
    return gulp.src(src.scss)
        .pipe(sass())
        .pipe(gulp.dest(src.css))
        .pipe(reload({stream: true}));
});

// Default task just compiles everything
// This is run when site is deployed!
gulp.task('default', ['sass'], function() {
});
