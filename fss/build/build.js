// Dependencies
var fs = require('fs');
var uglify = require('uglify-js');

// Settings
var FILE_ENCODING = 'utf-8',
    PROJECT_NAME  = 'fss',
    LICENSE       = '../LICENSE.md',
    SOURCE_DIR    = '../source',
    OUTPUT_DIR    = '../deploy',
    SCRIPTS       = [
        'Core.js',
        'Math.js',
        'Vector3.js',
        'Vector4.js',
        'Color.js',
        'Object.js',
        'Light.js',
        'Vertex.js',
        'Triangle.js',
        'Geometry.js',
        'Plane.js',
        'Material.js',
        'Mesh.js',
        'Scene.js',
        'Renderer.js',
        'CanvasRenderer.js',
        'WebGLRenderer.js',
        'SVGRenderer.js'
    ];

// Returns a path string from a list of path segments
function getPath() {
    return [].join.call(arguments, '/');
}

// Processes the specified files, creating a concatenated and a concatenated and minified output
function process() {
    var joined, license, unminified, minified;

    // Read the license
    license = fs.readFileSync(LICENSE, FILE_ENCODING);

    // Join the contents of all sources files into a single string
    joined = SCRIPTS.map(function(file) {
        return fs.readFileSync(getPath(SOURCE_DIR, file), FILE_ENCODING);
    }).join('\n');

    // Unminified
    unminified = license + '\n' + joined;

    // Minified
    minified = license + uglify.minify(joined, {fromString: true}).code;

    // Write out the concatenated file
    fs.writeFileSync(getPath(OUTPUT_DIR, PROJECT_NAME + '.js'), unminified, FILE_ENCODING);

    // Write out the minfied file
    fs.writeFileSync(getPath(OUTPUT_DIR, PROJECT_NAME + '.min.js'), minified, FILE_ENCODING);

    console.log('build complete');
}

// GO!
process();
