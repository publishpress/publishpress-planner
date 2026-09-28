  // Native Opentip Adapter
  // ======================

  // Use this adapter if you don't use a framework like jQuery and you don't
  // really care about oldschool browser compatibility.
var Adapter,
  hasProp = {}.hasOwnProperty;

Adapter = (function() {
  var dataValues, lastDataId;

  class Adapter {
    // Invoke callback as soon as dom is ready
    // Source: https://github.com/dperini/ContentLoaded/blob/master/src/contentloaded.js
    domReady(callback) {
      var add, doc, done, init, poll, pre, ref, rem, root, top, win;
      done = false;
      top = true;
      win = window;
      doc = document;
      if ((ref = doc.readyState) === "complete" || ref === "loaded") {
        return callback();
      }
      root = doc.documentElement;
      add = (doc.addEventListener ? "addEventListener" : "attachEvent");
      rem = (doc.addEventListener ? "removeEventListener" : "detachEvent");
      pre = (doc.addEventListener ? "" : "on");
      init = function(e) {
        if (e.type === "readystatechange" && doc.readyState !== "complete") {
          return;
        }
        (e.type === "load" ? win : doc)[rem](pre + e.type, init, false);
        if (!done) {
          done = true;
          return callback();
        }
      };
      poll = function() {
        var e;
        try {
          root.doScroll("left");
        } catch (error) {
          e = error;
          setTimeout(poll, 50);
          return;
        }
        return init("poll");
      };
      if (doc.readyState !== "complete") {
        if (doc.createEventObject && root.doScroll) {
          try {
            top = !win.frameElement;
          } catch (error) {}
          if (top) {
            poll();
          }
        }
        doc[add](pre + "DOMContentLoaded", init, false);
        doc[add](pre + "readystatechange", init, false);
        return win[add](pre + "load", init, false);
      }
    }

    // DOM
    // ===

      // Create the HTML passed as string
    create(htmlString) {
      var div;
      div = document.createElement("div");
      div.innerHTML = htmlString;
      return this.wrap(div.childNodes);
    }

    // Element handling
    // ----------------

      // Wrap the element in the framework
    wrap(element) {
      var el;
      if (!element) {
        element = [];
      } else if (typeof element === "string") {
        element = this.find(document.body, element);
        element = element ? [element] : [];
      } else if (element instanceof NodeList) {
        element = (function() {
          var i, len, results;
          results = [];
          for (i = 0, len = element.length; i < len; i++) {
            el = element[i];
            results.push(el);
          }
          return results;
        })();
      } else if (!(element instanceof Array)) {
        element = [element];
      }
      return element;
    }

    // Returns the unwrapped element
    unwrap(element) {
      return this.wrap(element)[0];
    }

    // Returns the tag name of the element
    tagName(element) {
      return this.unwrap(element).tagName;
    }

    // Returns or sets the given attribute of element
    attr(element, attr, value) {
      if (arguments.length === 3) {
        return this.unwrap(element).setAttribute(attr, value);
      } else {
        return this.unwrap(element).getAttribute(attr);
      }
    }

    // Returns or sets the given data of element
    data(element, name, value) {
      var dataId;
      dataId = this.attr(element, "data-id");
      if (!dataId) {
        dataId = ++lastDataId;
        this.attr(element, "data-id", dataId);
        dataValues[dataId] = {};
      }
      if (arguments.length === 3) {
        // Setter
        return dataValues[dataId][name] = value;
      } else {
        value = dataValues[dataId][name];
        if (value != null) {
          return value;
        }
        value = this.attr(element, `data-${Opentip.prototype.dasherize(name)}`);
        if (value) {
          dataValues[dataId][name] = value;
        }
        return value;
      }
    }

    // Finds elements by selector
    find(element, selector) {
      return this.unwrap(element).querySelector(selector);
    }

    // Finds all elements by selector
    findAll(element, selector) {
      return this.unwrap(element).querySelectorAll(selector);
    }

    // Updates the content of the element
    update(element, content, escape) {
      element = this.unwrap(element);
      if (escape) {
        element.innerHTML = ""; // Clearing the content
        return element.appendChild(document.createTextNode(content));
      } else {
        return element.innerHTML = content;
      }
    }

    // Appends given child to element
    append(element, child) {
      var unwrappedChild, unwrappedElement;
      unwrappedChild = this.unwrap(child);
      unwrappedElement = this.unwrap(element);
      return unwrappedElement.appendChild(unwrappedChild);
    }

    // Removes element
    remove(element) {
      var parentNode;
      element = this.unwrap(element);
      parentNode = element.parentNode;
      if (parentNode != null) {
        return parentNode.removeChild(element);
      }
    }

    // Add a class
    addClass(element, className) {
      return this.unwrap(element).classList.add(className);
    }

    // Remove a class
    removeClass(element, className) {
      return this.unwrap(element).classList.remove(className);
    }

    // Set given css properties
    css(element, properties) {
      var key, results, value;
      element = this.unwrap(this.wrap(element));
      results = [];
      for (key in properties) {
        if (!hasProp.call(properties, key)) continue;
        value = properties[key];
        results.push(element.style[key] = value);
      }
      return results;
    }

    // Returns an object with given dimensions
    dimensions(element) {
      var dimensions, revert;
      element = this.unwrap(this.wrap(element));
      dimensions = {
        width: element.offsetWidth,
        height: element.offsetHeight
      };
      if (!(dimensions.width && dimensions.height)) {
        // The element is probably invisible. So make it visible
        revert = {
          position: element.style.position || '',
          visibility: element.style.visibility || '',
          display: element.style.display || ''
        };
        this.css(element, {
          position: "absolute",
          visibility: "hidden",
          display: "block"
        });
        dimensions = {
          width: element.offsetWidth,
          height: element.offsetHeight
        };
        this.css(element, revert);
      }
      return dimensions;
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
      try {
        if (e.pageX || e.pageY) {
          pos.x = e.pageX;
          pos.y = e.pageY;
        } else if (e.clientX || e.clientY) {
          pos.x = e.clientX + document.body.scrollLeft + document.documentElement.scrollLeft;
          pos.y = e.clientY + document.body.scrollTop + document.documentElement.scrollTop;
        }
      } catch (error) {
        e = error;
      }
      return pos;
    }

    // Returns the offset of the element
    offset(element) {
      var offset;
      element = this.unwrap(element);
      offset = {
        top: element.offsetTop,
        left: element.offsetLeft
      };
      while (element = element.offsetParent) {
        offset.top += element.offsetTop;
        offset.left += element.offsetLeft;
        if (element !== document.body) {
          offset.top -= element.scrollTop;
          offset.left -= element.scrollLeft;
        }
      }
      return offset;
    }

    // Observe given eventName
    observe(element, eventName, observer) {
      // Firefox <= 3.6 needs the last optional parameter `useCapture`
      return this.unwrap(element).addEventListener(eventName, observer, false);
    }

    // Stop observing event
    stopObserving(element, eventName, observer) {
      // Firefox <= 3.6 needs the last optional parameter `useCapture`
      return this.unwrap(element).removeEventListener(eventName, observer, false);
    }

    // Perform an AJAX request and call the appropriate callbacks.
    ajax(options) {
      var e, ref, ref1, request;
      if (options.url == null) {
        throw new Error("No url provided");
      }
      if (window.XMLHttpRequest) {
        // Mozilla, Safari, ...
        request = new XMLHttpRequest();
      } else if (window.ActiveXObject) {
        try {
          // IE
          request = new ActiveXObject("Msxml2.XMLHTTP");
        } catch (error) {
          e = error;
          try {
            request = new ActiveXObject("Microsoft.XMLHTTP");
          } catch (error) {
            e = error;
          }
        }
      }
      if (!request) {
        throw new Error("Can't create XMLHttpRequest");
      }
      request.onreadystatechange = function() {
        if (request.readyState === 4) {
          try {
            if (request.status === 200) {
              if (typeof options.onSuccess === "function") {
                options.onSuccess(request.responseText);
              }
            } else {
              if (typeof options.onError === "function") {
                options.onError(`Server responded with status ${request.status}`);
              }
            }
          } catch (error) {
            e = error;
            if (typeof options.onError === "function") {
              options.onError(e.message);
            }
          }
          return typeof options.onComplete === "function" ? options.onComplete() : void 0;
        }
      };
      request.open((ref = (ref1 = options.method) != null ? ref1.toUpperCase() : void 0) != null ? ref : "GET", options.url);
      return request.send();
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

  Adapter.prototype.name = "native";

  lastDataId = 0;

  dataValues = {};

  return Adapter;

}).call(this);

// Add the adapter to the list
Opentip.addAdapter(new Adapter());
