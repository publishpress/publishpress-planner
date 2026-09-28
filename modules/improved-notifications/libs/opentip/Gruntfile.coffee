module.exports = (grunt) ->
  grunt.initConfig
    pkg: grunt.file.readJSON "package.json"


    stylus:
      options:
        compress: false
      default:
        files: [
          "css/opentip.css": "css/stylus/opentip.styl"
        ]

    coffee:
      default:
        expand: true
        options:
          bare: true
        cwd: "src/"
        src: ["*.coffee"]
        dest: "lib/"
        ext: ".js"

      test:
        files:
          "test/test.js": "test/src/*.coffee"

    concat:
      js:
        files:
          "downloads/opentip-jquery.js": ["lib/opentip.js", "lib/adapter-jquery.js"]
          "downloads/opentip-jquery-excanvas.js": ["downloads/opentip-jquery.js", "lib/tmp-excanvas.js"]

          "downloads/opentip-prototype.js": ["lib/opentip.js", "lib/adapter-prototype.js"]
          "downloads/opentip-prototype-excanvas.js": ["downloads/opentip-prototype.js", "lib/tmp-excanvas.js"]

          "downloads/opentip-native.js": ["lib/opentip.js", "lib/adapter-native.js", "lib/tmp-classlist.js",
            "lib/tmp-addeventlistener.js"]
          "downloads/opentip-native-excanvas.js": ["downloads/opentip-native.js", "lib/tmp-excanvas.js"]


    uglify:
      options:
        banner: """
                // Opentip v2.4.6
                // Copyright (c) 2009-2012
                // www.opentip.org
                // MIT Licensed

                """
      js:
        files: [
          "downloads/opentip-jquery.min.js": "downloads/opentip-jquery.js"
          "downloads/opentip-jquery-excanvas.min.js": "downloads/opentip-jquery-excanvas.js"
          "downloads/opentip-prototype.min.js": "downloads/opentip-prototype.js"
          "downloads/opentip-prototype-excanvas.min.js": "downloads/opentip-prototype-excanvas.js"
          "downloads/opentip-native.min.js": "downloads/opentip-native.js"
          "downloads/opentip-native-excanvas.min.js": "downloads/opentip-native-excanvas.js"
        ]


  grunt.loadNpmTasks "grunt-contrib-coffee"
  grunt.loadNpmTasks "grunt-contrib-stylus"
  grunt.loadNpmTasks "grunt-contrib-concat"
  grunt.loadNpmTasks "grunt-contrib-uglify"
  grunt.loadNpmTasks "grunt-contrib-clean"

  # Default tasks
  grunt.registerTask "default", ["downloads"]

  grunt.registerTask "css", "Compile the stylus files to css", ["stylus"]

  grunt.registerTask "js", "Compile coffeescript and create all download files", ["coffee"]

  grunt.registerTask "downloads", ["css", "js", "concat", "uglify"]
