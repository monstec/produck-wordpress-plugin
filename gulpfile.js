const gulp = require("gulp"),
  fs = require("fs"),
  log = require("fancy-log"),
  through2 = require("through2"),
  environments = require("gulp-environments"),
  preprocess = require("gulp-preprocess"),
  gfile = require("gulp-file"),
  gchanged = require("gulp-changed"),
  merge = require("merge-stream"),
  plumber = require("gulp-plumber"),
  livereload = require("gulp-livereload"),
  expect = require("gulp-expect-file"),
  sass = require("gulp-sass")(require("sass")),
  cssnano = require("cssnano"),
  postcss = require("gulp-postcss"),
  jshint = require("gulp-jshint"),
  stripDebug = require("gulp-strip-debug"),
  uglify = require("gulp-uglify"),
  rename = require("gulp-rename"),
  concat = require("gulp-concat"),
  sourcemaps = require("gulp-sourcemaps"),
  del = require("del"),
  chalk = require("chalk"),
  babel = require("gulp-babel"),
  zip = require("gulp-zip"),
  webpack = require("webpack"),
  webpackstream = require("webpack-stream"),
  extend = require("extend");


// *************************** //
// *** Build configuration *** //
// *************************** //

var pluginName = "produck";
var noop = () => through2.obj();

var paths = {
  libs: {
    js: [
      "node_modules/materialize-css/dist/js/materialize.min.js",
      "node_modules/shariff/dist/shariff.min.js",
      "node_modules/js-cookie/src/js.cookie.js",
      "node_modules/i18next/i18next.min.js",
      "node_modules/jquery-i18next/jquery-i18next.min.js",
      "node_modules/i18next-browser-languagedetector/i18nextBrowserLanguageDetector.min.js",
    ],
    css: [
      "node_modules/materialize-css/dist/css/materialize.min.css",
      "node_modules/shariff/dist/shariff.min.css",
    ],
  },
  source: {
    php: "src/php/**/*",
    css: ["src/css/**/*.scss", "src/css/**/*.css"],
    js: "src/js/**/*",
    img: "resources/img/**/*",
    static: "resources/static/**/*",
  },
  build: {
    root: "build",
    base: "build",
    php: "build",
    css: "build/css",
    js: "build/js",
    img: "build/img",
    maps: "/maps/", // this directory is meant for use with sourcemaps which uses a relative path
    temp: {
      mainJs: "build/temp/mainJs/",
    },
  },
  dist: {
    root: "dist",
    base: "dist/" + pluginName,
    php: "dist/" + pluginName,
    js: "dist/" + pluginName + "/js",
    css: "dist/" + pluginName + "/css",
    img: "dist/" + pluginName + "/img",
  },
};

var formatError = chalk.redBright;
var formatWarning = chalk.keyword("orange");

var development = environments.development;
var staging = environments.make("staging");
var production = environments.production;
var enableDebug = true;

var constants = {
  development: {
    webHost: "https://localhost",
  },
  staging: {
    webHost: "https://localhost/",
  },
  production: {
    webHost: "https://api.produck.de/",
  },
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

var errorHandlerFunction = function (err) {
  log(formatError(err));
  this.emit("end");
};

// ************************************ //
// *** Definition of task functions *** //
// ************************************ //

/*
 * Php sources are 'preprocess'ed and then placed into the build directory.
 */
function processPhp(destDir, deploy) {
  var stream = gulp
    .src(paths.source.php)
    .pipe(preprocess(preprocessConfig))
    .pipe(gulp.dest(destDir))
    .pipe(deploy ? gulp.dest(getCorrespondingDeploymentDir(destDir)) : noop());

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

function createConstantsFile() {
  var envSpecific = constants[getEnvironment()];
  var constantsObjString =
    "export default class Constants {\n  constructor() {\n";
  var key, value;
  for (key in envSpecific) {
    if (envSpecific.hasOwnProperty(key)) {
      // for JSHint w089
      value = envSpecific[key];
      if (typeof value === "string") {
        value = "'" + value + "'";
      }
      constantsObjString += "    this." + key + " = " + value + ";\n";
    }
  }

  constantsObjString += "\n  }\n}";

  return gfile("constants.js", constantsObjString, { src: true }).pipe(
    gulp.dest(paths.build.temp.mainJs)
  );
}

function prepareWebpack() {
  var mainJsStream = gulp
    .src(paths.source.js)
    .pipe(preprocess(preprocessConfig))
    .pipe(gulp.dest(paths.build.temp.mainJs));

  return mainJsStream;
}

// compile our own scripts using webpack (for dependencies) into a single output file
function runWebpack() {
  // load config from file but extend some variables defined within gulp
  let webpackconfig = extend({}, require("./webpack.config.js"), {
    entry: {
      produck: "./" + paths.build.temp.mainJs + "index.js",
    },
    mode: getEnvironment() === "production" ? "production" : "development", // necessary because webpack doesn't know 'staging'
    optimization: {
      minimize: false, // overwrite default of production-mode since minimising is currently done by gulp-plugin
    }
  });

  //gulp.src(['build/temp/*.js', 'src/main/scripts/**/**/*.js'])
  //gulp.src('src/'/* just create a pipe the actual files are not used */)
  var stream = gulp
    .src("src/js" /* just create a pipe the actual files are not used */)
    .pipe(webpackstream(webpackconfig, webpack))
    .on("error", errorHandlerFunction) // see https://github.com/shama/webpack-stream/issues/34    
    .pipe(gulp.dest(paths.build.js))
    .pipe(
      doDeployment
        ? gulp.dest(getCorrespondingDeploymentDir(paths.build.js)) : noop()
    )
    .pipe(!production() ? sourcemaps.write(paths.build.maps).on('error', log) : noop());

    return stream; // reload will be done by minify which always should runs after this task
}

// create minified version using gulp which is normally faster (enable only for production via variable?)
// the dependency to webpack ensures that minify is not run on an old version
function minify() {
  var stream = gulp
    .src(paths.build.js + "/" + pluginName + ".js")
    .pipe(sourcemaps.init({ loadMaps: true }))
    .pipe(rename({ suffix: ".min" }))
    .pipe(uglify())
    .pipe(sourcemaps.write(paths.build.maps).on("error", log))
    .pipe(gulp.dest(paths.build.js))
    .pipe(
      doDeployment
        ? gulp.dest(getCorrespondingDeploymentDir(paths.build.js))
        : noop()
    );

  return addReloadBehaviour(stream);
}

/*
 * Css and Sass processing.
 */
function processStyles(destDir, deploy) {
  var postCssPlugins = [cssnano()];

  var stream = gulp
    .src(paths.source.css)
    .pipe(plumber(errorHandlerFunction))
    .pipe(!production() ? sourcemaps.init() : noop())
    
    .pipe(sass({ errLogToConsole: true }))
    .pipe(postcss(postCssPlugins))
    .pipe(rename({ suffix: ".min" }))
    .pipe(
      !production()
        ? sourcemaps.write(paths.build.maps).on("error", log)
        : noop()
    )
    .pipe(gulp.dest(destDir))
    .pipe(deploy ? gulp.dest(getCorrespondingDeploymentDir(destDir)) : noop())
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

// /*
//  * Javascript processing.
//  */
// function processScripts(destDir, deploy, distribute) {
//     var jsFileName = pluginName + ".js";

//     var stream = gulp.src(paths.source.js)
//         .pipe(plumber(errorHandlerFunction))
//         .pipe(preprocess(preprocessConfig))
//         .pipe(!production() ? sourcemaps.init() : noop())
//         .pipe(jshint('.jshintrc'))
//         .pipe(jshint.reporter('default'))
//         .pipe(concat(jsFileName))
//         .pipe(babel({ presets: ['@babel/preset-env'] }))
//         // in production and in the distribution archive there is no need for the non-minified version
//         .pipe((!production() && !distribute) ? gulp.dest(destDir) : noop())
//         .pipe((!production() && deploy) ? gulp.dest(getCorrespondingDeploymentDir(destDir)) : noop())
//         .pipe(rename({ suffix: '.min' }))
//         .pipe(production() ? stripDebug() : noop())
//         .pipe(uglify())
//         .pipe(!production() ? sourcemaps.write(paths.build.maps).on('error', log) : noop())
//         .pipe(gulp.dest(destDir))
//         .pipe(deploy ? gulp.dest(getCorrespondingDeploymentDir(destDir)) : noop())
//         .pipe(plumber.stop());

//     return addReloadBehaviour(stream);
// }

// // for build task
// function processScriptsBuild() {
//     return processScripts(paths.build.js, doDeployment, false);
// }

// // for distribution task
// function processScriptsDist() {
//     return processScripts(paths.dist.js, false, true);
// }

/*
 * Include necessary third party libraries.
 */
function copyLibraries(destDirJs, destDirCss, deploy, distribute) {

   let streamJs = gulp.src(paths.libs.js, {"allowEmpty": true})
    .pipe(plumber(errorHandlerFunction))
    .pipe(expect(paths.libs.js))
    .pipe(gulp.dest(destDirJs))
    .pipe(deploy ? gulp.dest(getCorrespondingDeploymentDir(destDirJs)) : noop())
    .pipe(plumber.stop());

  let streamCss = gulp.src(paths.libs.css, {"allowEmpty": true})
    .pipe(plumber(errorHandlerFunction))
    .pipe(expect(paths.libs.css))
    .pipe(gulp.dest(destDirCss))
    .pipe(deploy ? gulp.dest(getCorrespondingDeploymentDir(destDirCss)) : noop())   
    .pipe(plumber.stop());

  return addReloadBehaviour(merge(streamJs, streamCss));
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
  var stream = gulp
    .src(paths.source.img)
    // only process changed images; this should save time for consecutive runs of this task or 'default'
    .pipe(gchanged(destDir))
    .pipe(gulp.dest(destDir))
    .pipe(deploy ? gulp.dest(getCorrespondingDeploymentDir(destDir)) : noop());

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
  var stream = gulp
    .src(paths.source.static)
    .pipe(gulp.dest(destDir))
    .pipe(deploy ? gulp.dest(getCorrespondingDeploymentDir(destDir)) : noop());

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
  var stream = gulp
    .src(paths.dist.base + "/**/*", { base: paths.dist.base + "/.." })
    .pipe(zip(pluginName + ".zip"))
    .pipe(gulp.dest(paths.dist.root));

  return merge(stream);
}

// define actual gulp tasks
var processScriptsBuild = gulp.series(
  createConstantsFile,
  prepareWebpack,
  runWebpack,
  minify
);
var processScriptsDist = gulp.series(
  createConstantsFile,
  prepareWebpack,
  runWebpack,
  minify
);

/*
 * Removes all files built during a previous run of any build task defined in this file.
 */
function clean() {
  return del(["build/**", "dist/**"]);
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

gulp.task('constants', createConstantsFile);
gulp.task('webpack', gulp.series(prepareWebpack, runWebpack));
gulp.task('minify', gulp.series('webpack', minify));
gulp.task("styles", processStylesBuild);
gulp.task("scripts", processScriptsBuild);
gulp.task("libs", copyLibrariesBuild);
gulp.task("images", copyChangedImagesBuild);
gulp.task("php", processPhpBuild);
gulp.task("static", copyStaticResourcesBuild);
gulp.task("clean", clean);
gulp.task(
    "default",    
    gulp.series(
        clean,
        logDeploymentStatus,
        gulp.parallel(copyChangedImagesBuild, processStylesBuild, processScriptsBuild, processPhpBuild, copyLibrariesBuild, copyStaticResourcesBuild)
    )
);
gulp.task(
    "dist",
    gulp.series(
        "clean",
        logDeploymentStatus,
        gulp.parallel(
            processPhpDist,
            copyChangedImagesDist,
            processStylesDist,
            processScriptsDist,
            copyLibrariesDist,
            copyStaticResourcesDist
        ),
        createDistributionArchive
    )
);
gulp.task("watch", watch);

// ************************************** //
// *** Definition of Helper functions *** //
// ************************************** //

function logDeploymentStatus(done) {
  log("Deployment will " + (!doDeployment ? "not " : "") + "be conducted.");
  done();
}

function getEnvironment() {
  if (development()) {
    return "development";
  } else if (staging()) {
    return "staging";
  } else if (production()) {
    return "production";
  } else {
    log(
      formatWarning(
        "ATTENTION! Environment Declaration not recognised, falling back to development."
      )
    );
    return "development";
  }
}

// This function will check if a deployment directory is defined (i.e. the environment variable
// WORDPRESS_PLUGINS_DIR is set and has a valid value). If the check is successful it will return true or false
// otherwise.
function checkDeploymentDir(targetDir) {
  // check if the environment variable exists and does have a value
  if (targetDir === undefined || targetDir === "") {
    log(
      formatWarning(
        "The environment variable WORDPRESS_PLUGINS_DIR is not set. Deployment to WordPress will not take place."
      )
    );
    return false;
  }

  // check if the directory defined in WORDPRESS_PLUGINS_DIR exists and is writeable for the current process
  try {
    fs.accessSync(targetDir, fs.constants.W_OK);
  } catch (err) {
    log(formatWarning(err.message));
    log(formatWarning("Deployment to webserver will not take place."));
    return false;
  }

  return true;
}

// Determines the exact file system position in the deployment directory that corresponds to a given
// build directory. This function is used to "convert" a build directory into a deployment directory by
// replacing the "build"-part in the value of the argument.
function getCorrespondingDeploymentDir(buildDir) {
  // For the sake of uniformity replace all backslashes in the defined deployment directory with slashes
  var prefix = deploymentDir.replace(/\\/g, "/");

  // If there is no trailing slash add one so that result can be concatenated with more directories
  prefix = prefix + (!prefix.endsWith("/") ? "/" : "");

  // all files have to be deployed in a subdirectory so change the deployment directory accordingly
  prefix += pluginName;

  // Remove the build-directory part from the given buildDir by replacing it with the deployment directory.
  var result = buildDir.replace(paths.build.root, prefix);

  return result;
}

// give your gulp stream to this method if you want to use the livereload plugin in your task
function addReloadBehaviour(stream) {
  stream.on("end", function () {
    livereload.reload();
  });

  return stream;
}

function doRelease(doneHandler) {
    if (!production()) {
        console.log('doRelease only enabled for production build')
        return doneHandler();
    }

    /*
    gitLastCommit.getLastCommit(function (err, commit) {
        // read commit object properties
        console.log(commit);
    });
    */

    // get commit hash
    git.revParse({ args: ' HEAD', quiet: true }, function (err, stdout) {
        if (err) throw err;

        var fileContents = 'commithash:\t\t' + stdout + "\n";

        // get commit date
        git.exec({ args: 'log -1 --pretty=format:%cd', quiet: true }, function (err, stdout) {
            if (err) throw err;

            fileContents += 'commitdate:\t\t' + stdout + "\n";

            fileContents += 'builddate:\t\t' + new Date() + "\n";

            // write to output folder
            require('fs').writeFileSync(paths.build.root + '/version', fileContents);
        });
    });

    // TODO use gulp-git and others to automatically create tag, push to server, deploy, ...

    return doneHandler();
}
