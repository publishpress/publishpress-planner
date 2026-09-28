// Ender Opentip Adapter
// =====================

// Uses ender packages

// Because $ is my favorite character
var hasProp = {}.hasOwnProperty;

(function($) {
  var Adapter, bean, reqwest;
  // Using bean as event handler
  bean = require("bean");
  // Using reqwest as AJAX lib
  reqwest = require("reqwest");
  // Augment ender
  $.ender({
    opentip: function(content, title, options) {
      return new Opentip(this, content, title, options);
    }
  }, true);
  Adapter = (function() {
    // And now the class
    class Adapter {
      // Simply using $.domReady
      domReady(callback) {
        return $.domReady(callback);
      }

      // DOM
      // ===

        // Using bonzo to create html
      create(html) {
        return $(html);
      }

      // Element handling
      // ----------------

        // Wraps the element in ender
      wrap(element) {
        element = $(element);
        if (element.length > 1) {
          throw new Error("Multiple elements provided.");
        }
        return element;
      }

      // Returns the unwrapped element
      unwrap(element) {
        return $(element).get(0);
      }

      // Returns the tag name of the element
      tagName(element) {
        return this.unwrap(element).tagName;
      }

      // Returns or sets the given attribute of element
      // It's important not to simply forward name and value because the value
      // is set whether or not the value argument is present
      attr(element, ...args) {
        return $(element).attr(...args);
      }

      // Returns or sets the given data of element
      // It's important not to simply forward name and value because the value
      // is set whether or not the value argument is present
      data(element, ...args) {
        return $(element).data(...args);
      }

      // Finds elements by selector
      find(element, selector) {
        return $(element).find(selector)[0];
      }

      // Finds all elements by selector
      findAll(element, selector) {
        return $(element).find(selector);
      }

      // Updates the content of the element
      update(element, content, escape) {
        element = $(element);
        if (escape) {
          return element.text(content);
        } else {
          return element.html(content);
        }
      }

      // Appends given child to element
      append(element, child) {
        return $(element).append(child);
      }

      // Removes element
      remove(element) {
        return $(element).remove();
      }

      // Add a class
      addClass(element, className) {
        return $(element).addClass(className);
      }

      // Remove a class
      removeClass(element, className) {
        return $(element).removeClass(className);
      }

      // Set given css properties
      css(element, properties) {
        return $(element).css(properties);
      }

      // Returns an object with given dimensions
      dimensions(element) {
        return $(element).dim();
      }

      // Returns the scroll offsets of current document
      scrollOffset() {
        return [window.pageXOffset || document.documentElement.scrollLeft || document.body.scrollLeft, window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop];
      }

      // Returns the dimensions of the viewport (currently visible browser area)
      viewportDimensions() {
        return {
          width: document.documentElement.clientWidth,
          height: document.documentElement.clientHeight
        };
      }

      // Returns an object with x and y
      mousePosition(e) {
        var pos;
        pos = {
          x: 0,
          y: 0
        };
        if (e == null) {
          e = window.event;
        }
        if (e == null) {
          return;
        }
        if (e.pageX || e.pageY) {
          pos.x = e.pageX;
          pos.y = e.pageY;
        } else if (e.clientX || e.clientY) {
          pos.x = e.clientX + document.body.scrollLeft + document.documentElement.scrollLeft;
          pos.y = e.clientY + document.body.scrollTop + document.documentElement.scrollTop;
        }
        return pos;
      }

      // Returns the offset of the element
      offset(element) {
        var offset;
        offset = $(element).offset();
        return {
          top: offset.top,
          left: offset.left
        };
      }

      // Observe given eventName
      observe(element, eventName, observer) {
        return $(element).on(eventName, observer);
      }

      // Stop observing event
      stopObserving(element, eventName, observer) {
        return $(element).unbind(eventName, observer);
      }

      // Perform an AJAX request and call the appropriate callbacks.
      ajax(options) {
        var ref, ref1;
        if (options.url == null) {
          throw new Error("No url provided");
        }
        return reqwest({
          url: options.url,
          type: 'html',
          method: (ref = (ref1 = options.method) != null ? ref1.toUpperCase() : void 0) != null ? ref : "GET",
          error: function(resp) {
            return typeof options.onError === "function" ? options.onError(`Server responded with status ${resp.status}`) : void 0;
          },
          success: function(resp) {
            return typeof options.onSuccess === "function" ? options.onSuccess(resp) : void 0;
          },
          complete: function() {
            return typeof options.onComplete === "function" ? options.onComplete() : void 0;
          }
        });
      }

      // Utility functions
      // =================

        // Creates a shallow copy of the object
      clone(object) {
        var key, newObject, val;
        newObject = {};
        for (key in object) {
          if (!hasProp.call(object, key)) continue;
          val = object[key];
          newObject[key] = val;
        }
        return newObject;
      }

      // Copies all properties from sources to target
      extend(target, ...sources) {
        var i, key, len, source, val;
        for (i = 0, len = sources.length; i < len; i++) {
          source = sources[i];
          for (key in source) {
            if (!hasProp.call(source, key)) continue;
            val = source[key];
            target[key] = val;
          }
        }
        return target;
      }

    };

    Adapter.prototype.name = "ender";

    return Adapter;

  }).call(this);
  // Add the adapter to the list
  return Opentip.addAdapter(new Adapter());
})(ender);
