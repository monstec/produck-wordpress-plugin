var gulp = require('gulp'),
    environments = require('gulp-environments'),
    noop = require('gulp-noop'),
    fs = require('fs'),
    preprocess = require("gulp-preprocess"),
    log = require('fancy-log'),
    gchanged = require('gulp-changed'),
    merge = require('merge-stream'),
    plumber = require('gulp-plumber'),
    livereload = require('gulp-livereload'),
    expect = require('gulp-expect-file'),
    sass = require('gulp-sass'),
    autoprefixer = require('autoprefixer'),
    cssnano = require('cssnano'),
    postcss = require('gulp-postcss'),
    jshint = require('gulp-jshint'),
    stripDebug = require('gulp-strip-debug'),
    uglify = require('gulp-uglify'),
    rename = require('gulp-rename'),
    concat = require('gulp-concat'),
    sourcemaps = require('gulp-sourcemaps'),
    del = require('del'),
    chalk = require('chalk'),
    babel = require('gulp-babel'),
    zip = require('gulp-zip');


// *************************** //
// *** Build configuration *** //
// *************************** //

var pluginName = 'produck';

var paths = {
    "libs": {
        "js": [
            'node_modules/shariff/dist/shariff.min.js',
            'node_modules/js-cookie/src/js.cookie.js',
        ],
        "css": [
            'node_modules/shariff/dist/shariff.min.css',
        ]
    },
    "source": {
        "php": "src/php/**/*",
        "css": ['src/css/**/*.scss', 'src/css/**/*.css'],
        "js": "src/js/**/*",
        "img": "resources/img/**/*",
        "static": "resources/static/**/*"
    },
    "build": {
        "root": "build",
        "base": "build",
        "php": "build",
        "css": "build/css",
        "js": "build/js",
        "img": "build/img",
        "maps": "/maps/" // this directory is meant for use with sourcemaps which uses a relative path
    },
    "dist": {
        "root": "dist",
        "base": "dist/" + pluginName,
        "php": "dist/" + pluginName,
        "js": "dist/" + pluginName + "/js",
        "css": "dist/" + pluginName + "/css",
        "img": "dist/" + pluginName + "/img",
    }
};

var formatError = chalk.redBright;
var formatWarning = chalk.keyword('orange');

var errorHandlerFunction = function (err) {
    log(formatError(err));
    this.emit('end');
};

// activate production build by passing '--env production' to gulp
var production = environments.production;
var development = environments.development;
var enableDebug = true;

var deploymentDir = process.env.WORDPRESS_PLUGINS_DIR;
var doDeployment = checkDeploymentDir(deploymentDir);

// environment variable for preprocess context
var preprocessConfig = {};
if (production()) {
    preprocessConfig.context = { ENV: production.$name, DEBUG: enableDebug };
} else {
    preprocessConfig.context = { ENV: development.$name, DEBUG: enableDebug };
}


// ************************************ //
// *** Definition of task functions *** //
// ************************************ //

/*
 * Php sources are 'preprocess'ed and then placed into the build directory.
 */
function processPhp(destDir, deploy) {
    var stream = gulp.src(paths.source.php)
        .pipe(preprocess(preprocessConfig))
        .pipe(gulp.dest(destDir))
        .pipe((deploy ? gulp.dest(getCorrespondingDeploymentDir(destDir)) : noop()));

    return stream;

    return addReloadBehaviour(stream);
}

// for build task
function processPhpBuild() {
    return processPhp(paths.build.php, doDeployment);
}

// for distribution task
function processPhpDist() {
    return processPhp(paths.dist.php, false);
}

/*
 * Css and Sass processing.
 */
function processStyles(destDir, deploy) {
    var postCssPlugins = [
        autoprefixer({ browsers: ['last 2 version'] }),
        cssnano()
    ];

    var stream = gulp.src(paths.source.css)
        .pipe(plumber(errorHandlerFunction))
        .pipe(!production() ? sourcemaps.init() : noop())
        .pipe(sass({ errLogToConsole: false }))
        // autoprefixer currently breaks sourcemaps according to the gulp-sourcemaps-wiki, unless
        // used in conjunction with postcss. The configuration of postcss resides in package.
        .pipe(postcss(postCssPlugins))
        .pipe(rename({ suffix: '.min' }))
        .pipe(!production() ? sourcemaps.write(paths.build.maps).on('error', log) : noop())
        .pipe(gulp.dest(destDir))
        .pipe((deploy ? gulp.dest(getCorrespondingDeploymentDir(destDir)) : noop()))
        .pipe(plumber.stop());

    return addReloadBehaviour(stream);
}

// for build task
function processStylesBuild() {
    return processStyles(paths.build.css, doDeployment);
}

// for distribution task
function processStylesDist() {
    return processStyles(paths.dist.css, false);
}

/*
 * Javascript processing.
 */
function processScripts(destDir, deploy, distribute) {
    var jsFileName = pluginName + ".js";

    var stream = gulp.src(paths.source.js)
        .pipe(plumber(errorHandlerFunction))
        .pipe(preprocess(preprocessConfig))
        .pipe(!production() ? sourcemaps.init() : noop())
        .pipe(jshint('.jshintrc'))
        .pipe(jshint.reporter('default'))
        .pipe(concat(jsFileName))
        .pipe(babel({ presets: ['env'] }))
        // in production and in the distribution archive there is no need for the non-minified version
        .pipe((!production() && !distribute) ? gulp.dest(destDir) : noop())
        .pipe((!production() && deploy) ? gulp.dest(getCorrespondingDeploymentDir(destDir)) : noop())
        .pipe(rename({ suffix: '.min' }))
        .pipe(production() ? stripDebug() : noop())
        .pipe(uglify())
        .pipe(!production() ? sourcemaps.write(paths.build.maps).on('error', log) : noop())
        .pipe(gulp.dest(destDir))
        .pipe(deploy ? gulp.dest(getCorrespondingDeploymentDir(destDir)) : noop())
        .pipe(plumber.stop());

    return addReloadBehaviour(stream);
}

// for build task
function processScriptsBuild() {
    return processScripts(paths.build.js, doDeployment, false);
}

// for distribution task
function processScriptsDist() {
    return processScripts(paths.dist.js, false, true);
}

/*
 * Include necessary third party libraries.
 */
function copyLibraries(destDirJs, destDirCss, deploy, distribute) {
    var jsStream = gulp.src(paths.libs.js)
        .pipe(expect(paths.libs.js))
        .pipe(gulp.dest(destDirJs))
        .pipe((!production() && !distribute) ? gulp.dest(destDirJs) : noop())
        .pipe((deploy ? gulp.dest(getCorrespondingDeploymentDir(paths.build.js)) : noop()));
        var cssStream = gulp.src(paths.libs.css)
        .pipe(expect(paths.libs.css))
        .pipe(gulp.dest(destDirCss))
        .pipe((!production() && !distribute) ? gulp.dest(destDirCss) : noop())
        .pipe((deploy ? gulp.dest(getCorrespondingDeploymentDir(paths.build.css)) : noop()));

    return addReloadBehaviour(merge(jsStream, cssStream));
}

// for build task
function copyLibrariesBuild() {
    return copyLibraries(paths.build.js, paths.build.css, doDeployment, false);
}

// for distribution task
function copyLibrariesDist() {
    return copyLibraries(paths.dist.js, paths.dist.css, false, true);
}

/*
 * Images are just copied into the build directory.
 */
function copyChangedImages(destDir, deploy) {
    var stream = gulp.src(paths.source.img)
        // only process changed images; this should save time for consecutive runs of this task or 'default'
        .pipe(gchanged(destDir))
        .pipe(gulp.dest(destDir))
        .pipe((deploy ? gulp.dest(getCorrespondingDeploymentDir(destDir)) : noop()));

    return addReloadBehaviour(stream);
}

// for build task
function copyChangedImagesBuild() {
    return copyChangedImages(paths.build.img, doDeployment);
}

// for distribution task
function copyChangedImagesDist() {
    return copyChangedImages(paths.dist.img, false);
}

/*
 * Images are just copied into the build directory.
 */
function copyStaticResources(destDir, deploy) {
    var stream = gulp.src(paths.source.static)
        .pipe(gulp.dest(destDir))
        .pipe((deploy ? gulp.dest(getCorrespondingDeploymentDir(destDir)) : noop()));

    return addReloadBehaviour(stream);
}

// for build task
function copyStaticResourcesBuild() {
    return copyStaticResources(paths.build.base, doDeployment);
}

// for distribution task
function copyStaticResourcesDist() {
    return copyStaticResources(paths.dist.base, false);
}

/*
 * Creates a zip archive for each module that can be installed via WPs plugin section.
 */
function createDistributionArchive() {
    // The base folder of the src-directive must be changed so that gulp-zip includes the base directory of the
    // particular module. This is necessary because WP requires the module-zip to contain this base directory
    // and not only the files in it. Otherwise it is not even possible to install the module.
    var stream = gulp.src(paths.dist.base + '/**/*', {base:paths.dist.base + '/..'})
        .pipe(zip(pluginName + '.zip'))
        .pipe(gulp.dest(paths.dist.root));

    return merge(stream);
}

/*
 * Removes all files built during a previous run of any build task defined in this file.
 */
function clean() {
    return del(['build/**', 'dist/**']);
}

/*
 * Start watching source files for automatic build initiation.
 */
function watch() {
    // Start the live reload server that will trigger a reload in the browser when the watch task detects any
    // changes. For this to work a corresponding plugin has to be installed in the browser.
    livereload.listen();

    // Watch php sources
    gulp.watch(paths.source.php, processPhpBuild);

    // Watch css and sass files
    gulp.watch(paths.source.css, processStylesBuild);

    // Watch javascript code
    gulp.watch(paths.source.js, processScriptsBuild);

    // Watch images
    gulp.watch(paths.source.img, copyChangedImagesBuild);
}


// *************************************** //
// *** Definition of actual gulp tasks *** //
// *************************************** //

gulp.task('styles', processStylesBuild);
gulp.task('scripts', processScriptsBuild);
gulp.task('php', processPhpBuild);
gulp.task('images', copyChangedImagesBuild);
gulp.task('libs', copyLibrariesBuild);
gulp.task('static', copyStaticResourcesBuild);

gulp.task('clean', clean);
gulp.task('watch', watch);

gulp.task('default', gulp.series('clean', logDeploymentStatus, gulp.parallel('php', 'images', 'styles', 'scripts', 'libs', 'static')));

gulp.task('dist', gulp.series('clean', logDeploymentStatus,
                              gulp.parallel(processPhpDist, copyChangedImagesDist, processStylesDist, processScriptsDist,
                                            copyLibrariesDist, copyStaticResourcesDist),
                              createDistributionArchive));


// ************************************** //
// *** Definition of Helper functions *** //
// ************************************** //

function logDeploymentStatus(done) {
    log("Deployment will " + ((!doDeployment) ? "not " : "") + "be conducted.");
    done();
}

// This function will check if a deployment directory is defined (i.e. the environment variable
// WORDPRESS_PLUGINS_DIR is set and has a valid value). If the check is successful it will return true or false
// otherwise.
function checkDeploymentDir(targetDir) {
    // check if the environment variable exists and does have a value
    if (targetDir === undefined || targetDir === '') {
        log(formatWarning('The environment variable WORDPRESS_PLUGINS_DIR is not set. Deployment to WordPress will not take place.'));
        return false;
    }

    // check if the directory defined in WORDPRESS_PLUGINS_DIR exists and is writeable for the current process
    try {
        fs.accessSync(targetDir, fs.constants.W_OK);
    } catch (err) {
        log(formatWarning(err.message));
        log(formatWarning('Deployment to webserver will not take place.'));
        return false;
    }

    return true;
}

// Determines the exact file system position in the deployment directory that corresponds to a given
// build directory. This function is used to "convert" a build directory into a deployment directory by
// replacing the "build"-part in the value of the argument.
function getCorrespondingDeploymentDir(buildDir) {
    // For the sake of uniformity replace all backslashes in the defined deployment directory with slashes
    var prefix = deploymentDir.replace(/\\/g, '/');

    // If there is no trailing slash add one so that result can be concatenated with more directories
    prefix = prefix + (!prefix.endsWith('/') ? '/' : '');

    // all files have to be deployed in a subdirectory so change the deployment directory accordingly
    prefix += pluginName;

    // Remove the build-directory part from the given buildDir by replacing it with the deployment directory.
    var result = buildDir.replace(paths.build.root, prefix);

    return result;
}

// give your gulp stream to this method if you want to use the livereload plugin in your task
function addReloadBehaviour(stream) {
    stream.on('end', function () {
        livereload.reload();
    });

    return stream;
}
