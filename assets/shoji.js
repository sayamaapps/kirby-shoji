/*!
 * Shoji — click-to-load for Kirby CMS. https://github.com/simsimmal/kirby-shoji
 *
 * A [data-shoji] placeholder loads its iframe (and, first, an optional
 * provider script) only when the visitor clicks it. Every address comes
 * from the placeholder's data attributes and points to this site; the site
 * redirects to the provider. No inline script, no cookies, no storage —
 * the next page asks again.
 *
 * Events on the placeholder (they bubble), detail = { provider }:
 *   shoji:open    clicked, nothing loaded yet
 *   shoji:script  the provider script has loaded (or failed), the iframe comes next
 *   shoji:loaded  the iframe fired load (or 5 seconds passed); the placeholder
 *                 content is gone, the container has the class shoji-opened
 * While loading, the container has the class shoji-loading and still holds
 * the placeholder content next to the new iframe.
 */
(function () {
  'use strict';

  var scripts = {}; // src -> list of callbacks while loading, true when done

  function emit(box, name) {
    var event;
    var detail = { provider: box.getAttribute('data-shoji-provider') };
    if (typeof window.CustomEvent === 'function') {
      event = new CustomEvent(name, { bubbles: true, detail: detail });
    } else {
      event = document.createEvent('CustomEvent');
      event.initCustomEvent(name, true, false, detail);
    }
    box.dispatchEvent(event);
  }

  function loadScript(src, done) {
    if (scripts[src] === true) {
      done();
      return;
    }
    if (scripts[src]) {
      scripts[src].push(done);
      return;
    }
    scripts[src] = [done];
    var script = document.createElement('script');
    // A failed script must not block the iframe: the order form or player
    // usually works without its helper script.
    script.onload = script.onerror = function () {
      var waiting = scripts[src];
      scripts[src] = true;
      for (var i = 0; i < waiting.length; i++) {
        waiting[i]();
      }
    };
    script.async = true;
    script.src = src;
    document.head.appendChild(script);
  }

  function insertFrame(box) {
    var frame = document.createElement('iframe');
    var extra = box.getAttribute('data-shoji-frame-class');
    frame.className = extra ? 'shoji-frame ' + extra : 'shoji-frame';
    frame.title = box.getAttribute('data-shoji-title') || '';
    frame.setAttribute('allow', box.getAttribute('data-shoji-allow') || '');
    frame.setAttribute('allowfullscreen', '');

    var finished = false;
    function finish() {
      if (finished) {
        return;
      }
      finished = true;
      var hadFocus = box.contains(document.activeElement);
      var children = Array.prototype.slice.call(box.children);
      for (var i = 0; i < children.length; i++) {
        if (children[i] !== frame) {
          box.removeChild(children[i]);
        }
      }
      box.classList.remove('shoji-loading');
      box.classList.add('shoji-opened');
      box.setAttribute('data-shoji-state', 'opened');
      if (hadFocus) {
        frame.focus();
      }
      emit(box, 'shoji:loaded');
    }

    // Listen before setting src: a fast or cached frame can finish first.
    // The timeout covers browsers and extensions that swallow the
    // cross-origin load event although the frame did navigate.
    frame.addEventListener('load', finish);
    window.setTimeout(finish, 5000);
    frame.src = box.getAttribute('data-shoji-frame');
    box.appendChild(frame);
  }

  function open(box) {
    if (box.hasAttribute('data-shoji-state') || !box.getAttribute('data-shoji-frame')) {
      return;
    }
    box.setAttribute('data-shoji-state', 'loading');
    box.classList.add('shoji-loading');
    emit(box, 'shoji:open');

    var script = box.getAttribute('data-shoji-script');
    if (script) {
      loadScript(script, function () {
        emit(box, 'shoji:script');
        insertFrame(box);
      });
    } else {
      insertFrame(box);
    }
  }

  document.addEventListener('click', function (event) {
    var target = event.target;
    var box = target && target.closest ? target.closest('[data-shoji]') : null;
    if (!box || box.hasAttribute('data-shoji-state')) {
      return;
    }
    // Other links in the placeholder (the privacy policy) work as links.
    var link = target.closest('a');
    if (link && !link.classList.contains('shoji-button')) {
      return;
    }
    // Middle click, Ctrl/Cmd click: the button is a real link to the provider.
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    event.preventDefault();
    open(box);
  });
})();
