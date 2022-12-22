/*
 * This is default barebone for gulp, add your own modifications here
 * It also serves as an example of how to use browser-sync in this environment
 */
const gulp = require('gulp');
const browserSync = require('browser-sync');
const sass = require('gulp-sass')(require('sass'));
const reload = browserSync.reload;

/*
 * src array contains the files which the gulp will be watching
 * Choose your theme over here
 */
var src = {
  scss: 'htdocs/wp-content/themes/yourtheme/scss/*.scss',
  css: 'htdocs/wp-content/themes/yourtheme/css',
  php: [
    'htdocs/wp-content/themes/*/*.php',
    'htdocs/wp-content/plugins/*/*.php',
    'htdocs/wp-content/mu-plugins/*/*.php',
  ],
};

// Compile sass into CSS
gulp.task('sass', () => {
  return gulp
    .src(src.scss)
    .pipe(sass())
    .pipe(gulp.dest(src.css))
    .pipe(reload({ stream: true }));
});

// Serve all files through browser-sync
gulp.task(
  'serve',
  gulp.series('sass', () => {
    // Initialize browsersync
    // Nginx is configured to use any service in port 1337
    // as middleware to WordPress in vagrant environment
    browserSync.init({
      // browsersync with a php server
      proxy: `https://${process.env.DEFAULT_DOMAIN}`,
      open: false,
      port: 1337,
      ui: {
        port: 1338,
      },
      notify: true,
    });
    // Watch sass files and compile them. Use polling because the Virtualbox
    // shared folders implementation does not emit file system events from the
    // host to the VM: https://github.com/floatdrop/gulp-watch/issues/213.
    gulp.watch(src.scss, { usePolling: true }, gulp.series('sass'));
    // Update the page automatically if php files change
    gulp.watch(src.php, { usePolling: true }).on('change', reload);
  })
);

// Give another name for serve
gulp.task('watch', gulp.series('serve'));

// Default task just compiles everything
// This is run when site is deployed!
gulp.task('default', gulp.series('sass'));
