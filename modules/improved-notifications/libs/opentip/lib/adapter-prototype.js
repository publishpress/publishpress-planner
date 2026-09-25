// Prototype Opentip Adapter
// ======================

// Uses the prototype framework
(function() {
  var Adapter, isArrayOrNodeList;
  Element.addMethods({
    addTip: function(element, content, title, options) {
      return new Opentip(element, content, title, options);
    }
  });
  // Needs this function because of IE8
  isArrayOrNodeList = function(element) {
    if ((element instanceof Array) || ((element != null) && typeof element.length === 'number' && typeof element.item === 'function' && typeof element.nextNode === 'function' && typeof element.reset === 'function')) {
      return true;
    }
    return false;
  };
  Adapter = (function() {
    // And now the class
    class Adapter {
      domReady(callback) {
        if (document.loaded) {
          return callback();
        } else {
          return $(document).observe("dom:loaded", callback);
        }
      }

      // DOM
      // ===

        // Using bonzo to create html
      create(html) {
        return new Element('div').update(html).childElements();
      }

      // Element handling
      // ----------------

        // Wraps the element
      wrap(element) {
        if (isArrayOrNodeList(element)) {
          if (element.length > 1) {
            throw new Error("Multiple elements provided.");
          }
          element = this.unwrap(element);
        } else if (typeof element === "string") {
          element = $$(element)[0];
        }
        return $(element);
      }

      // Returns the unwrapped element
      unwrap(element) {
        if (isArrayOrNodeList(element)) {
          return element[0];
        } else {
          return element;
        }
      }

      // Returns the tag name of the element
      tagName(element) {
        return this.unwrap(element).tagName;
      }

      // Returns or sets the given attribute of element

      // It's important not to simply forward name and value because the value
      // is set whether or not the value argument is present
      attr(element, ...args) {
        if (args.length === 1) {
          return this.wrap(element).readAttribute(args[0]);
        } else {
          return this.wrap(element).writeAttribute(...args);
        }
      }

      // Returns or sets the given data of element
      // It's important not to simply forward name and value because the value
      // is set whether or not the value argument is present
      data(element, name, value) {
        var arg;
        this.wrap(element);
        if (arguments.length > 2) {
          return element.store(name, value);
        } else {
          arg = element.readAttribute(`data-${name.underscore().dasherize()}`);
          if (arg != null) {
            return arg;
          }
          return element.retrieve(name);
        }
      }

      // Finds elements by selector
      find(element, selector) {
        return this.wrap(element).select(selector)[0];
      }

      // Finds all elements by selector
      findAll(element, selector) {
        return this.wrap(element).select(selector);
      }

      // Updates the content of the element
      update(element, content, escape) {
        return this.wrap(element).update(escape ? content.escapeHTML() : content);
      }

      // Appends given child to element
      append(element, child) {
        return this.wrap(element).insert(this.wrap(child));
      }

      // Removes element
      remove(element) {
        return this.wrap(element).remove();
      }

      // Add a class
      addClass(element, className) {
        return this.wrap(element).addClassName(className);
      }

      // Remove a class
      removeClass(element, className) {
        return this.wrap(element).removeClassName(className);
      }

      // Set given css properties
      css(element, properties) {
        return this.wrap(element).setStyle(properties);
      }

      // Returns an object with given dimensions
      dimensions(element) {
        return this.wrap(element).getDimensions();
      }

      // Returns the scroll offsets of current document
      scrollOffset() {
        var offsets;
        offsets = document.viewport.getScrollOffsets();
        return [offsets.left, offsets.top];
      }

      // Returns the dimensions of the viewport (currently visible browser area)
      viewportDimensions() {
        return document.viewport.getDimensions();
      }

      // Returns an object with x and y
      mousePosition(e) {
        if (e == null) {
          return null;
        }
        return {
          x: Event.pointerX(e),
          y: Event.pointerY(e)
        };
      }

      // Returns the offset of the element
      offset(element) {
        var offset;
        offset = this.wrap(element).cumulativeOffset();
        return {
          left: offset.left,
          top: offset.top
        };
      }

      // Observe given eventName
      observe(element, eventName, observer) {
        return Event.observe(this.wrap(element), eventName, observer);
      }

      // Stop observing event
      stopObserving(element, eventName, observer) {
        return Event.stopObserving(this.wrap(element), eventName, observer);
      }

      // Perform an AJAX request and call the appropriate callbacks.
      ajax(options) {
        var ref, ref1;
        if (options.url == null) {
          throw new Error("No url provided");
        }
        return new Ajax.Request(options.url, {
          method: (ref = (ref1 = options.method) != null ? ref1.toUpperCase() : void 0) != null ? ref : "GET",
          onSuccess: function(response) {
            return typeof options.onSuccess === "function" ? options.onSuccess(response.responseText) : void 0;
          },
          onFailure: function(response) {
            return typeof options.onError === "function" ? options.onError(`Server responded with status ${response.status}`) : void 0;
          },
          onComplete: function() {
            return typeof options.onComplete === "function" ? options.onComplete() : void 0;
          }
        });
      }

      // Utility functions
      // =================

        // Creates a shallow copy of the object
      clone(object) {
        return Object.clone(object);
      }

      // Copies all properties from sources to target
      extend(target, ...sources) {
        var i, len, source;
        for (i = 0, len = sources.length; i < len; i++) {
          source = sources[i];
          Object.extend(target, source);
        }
        return target;
      }

    };

    Adapter.prototype.name = "prototype";

    return Adapter;

  }).call(this);
  // Add the adapter to the list
  return Opentip.addAdapter(new Adapter());
})();
