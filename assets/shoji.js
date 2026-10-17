/*!
 * Shoji — click-to-load for Kirby CMS. https://github.com/simsimmal/kirby-shoji
 *
 * A [data-shoji] placeholder loads its iframe (and, first, optional
 * provider scripts) only when the visitor clicks it. Every address comes
 * from the placeholder's data attributes and points to this site; the site
 * redirects to the provider. No inline script, no cookies, no storage —
 * the next page asks again.
 *
 * Kind 'html' (data-shoji-html): the site's own markup (a newsletter form,
 * say) is fetched from this site, put into the page after the provider's
 * stylesheets have loaded, and only then are the provider's scripts loaded.
 * Scripts inside the markup do not run; the provider's scripts come from
 * data-shoji-script like everywhere else.
 *
 * Events on the placeholder (they bubble), detail = { provider }:
 *   shoji:open      clicked, nothing loaded yet
 *   shoji:inserted  kind 'html': the markup is in the page, the placeholder content
 *                   is gone, the provider scripts are not loaded yet. The place to
 *                   fill hidden form fields.
 *   shoji:script    the provider scripts have loaded (or failed, or 5 seconds passed); for
 *                   an iframe, the iframe comes next
 *   shoji:loaded    the iframe fired load (or 5 seconds passed), or the markup and
 *                   its scripts are there (or 5 seconds passed); the container has the
 *                   class shoji-opened
 * While loading, the container has the class shoji-loading and still holds
 * the placeholder content.
 *
 * No step holds the content back for more than 5 seconds: the provider's
 * scripts, the stylesheets, the markup and the iframe each get that long.
 * Scripts that arrive later still load, in their order, and run; they never
 * open anything twice.
 */
(function () {
  'use strict';

  var WAIT = 5000; // ms one step (scripts, stylesheets, markup, iframe) may hold the content back

  var loading = {}; // script or stylesheet address -> callbacks while loading, true when done

  /**
   * The address only if it is one of Shoji's own on this host
   * (/shoji/frame|html|script|style/…). Someone who manages to inject
   * HTML with data-shoji attributes (a sanitizer keeps data-* attributes)
   * must not get a javascript: frame or a foreign script out of this file.
   */
  function own(value, kind) {
    var url;
    try {
      url = new URL(value || '', window.location.href);
    } catch (e) {
      return null;
    }
    var path = new RegExp('/shoji/' + kind + '/[a-z][a-z0-9-]*/[A-Za-z0-9_-]+$');
    if ((url.protocol !== 'https:' && url.protocol !== 'http:') ||
        url.hostname !== window.location.hostname ||
        !path.test(url.pathname)) {
      return null;
    }
    return url.href;
  }

  /**
   * Own addresses separated by spaces, as a list; [] for none, null if
   * any of them is not Shoji's own (then nothing opens at all).
   */
  function ownList(value, kind) {
    var parts = (value || '').split(/\s+/);
    var list = [];
    for (var i = 0; i < parts.length; i++) {
      if (parts[i] === '') {
        continue;
      }
      var url = own(parts[i], kind);
      if (!url) {
        return null;
      }
      list.push(url);
    }
    return list;
  }

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

  /** Loads a script or stylesheet once per page; done() also when it fails. */
  function load(tag, src, done) {
    if (loading[src] === true) {
      done();
      return;
    }
    if (loading[src]) {
      loading[src].push(done);
      return;
    }
    loading[src] = [done];
    var element = document.createElement(tag);
    // A failed script must not block the iframe: the order form or player
    // usually works without its helper script.
    element.onload = element.onerror = function () {
      var waiting = loading[src];
      loading[src] = true;
      for (var i = 0; i < waiting.length; i++) {
        waiting[i]();
      }
    };
    if (tag === 'link') {
      element.rel = 'stylesheet';
      element.href = src;
    } else {
      element.async = true;
      element.src = src;
    }
    document.head.appendChild(element);
  }

  /**
   * The scripts one after the other, in their order (a later one may need an
   * earlier one). A script that neither loads nor fails holds the content
   * back for WAIT at most: then done() runs without it. The chain goes on in
   * the background, so a late script still loads and runs in its place, but
   * done() and the event come once only.
   */
  function loadScripts(box, list, done) {
    var i = 0;
    var finished = false;
    function finish() {
      if (finished) {
        return;
      }
      finished = true;
      if (list.length) {
        emit(box, 'shoji:script');
      }
      done();
    }
    if (list.length) {
      window.setTimeout(finish, WAIT);
    }
    (function next() {
      if (i < list.length) {
        load('script', list[i++], next);
        return;
      }
      finish();
    })();
  }

  /** Everything in the box except keep goes; the box is opened. */
  function reveal(box, keep) {
    var hadFocus = box.contains(document.activeElement);
    var children = Array.prototype.slice.call(box.children);
    for (var i = 0; i < children.length; i++) {
      if (children[i] !== keep) {
        box.removeChild(children[i]);
      }
    }
    box.classList.remove('shoji-loading');
    box.classList.add('shoji-opened');
    box.setAttribute('data-shoji-state', 'opened');
    // The button had the focus and is gone: keyboard and screen reader
    // continue at the new content instead of the top of the page.
    if (hadFocus) {
      keep.focus();
    }
  }

  function insertFrame(box, src) {
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
      reveal(box, frame);
      emit(box, 'shoji:loaded');
    }

    // Listen before setting src: a fast or cached frame can finish first.
    // The timeout covers browsers and extensions that swallow the
    // cross-origin load event although the frame did navigate.
    frame.addEventListener('load', finish);
    window.setTimeout(finish, WAIT);
    frame.src = src;
    box.appendChild(frame);
  }

  /**
   * Kind 'html': the markup from the site and the provider's stylesheets
   * at the same time; in the page once both are there (a stylesheet that
   * hangs is given 5 seconds); then the provider's scripts, which look for
   * their form when they run. Markup that cannot be fetched, or does not
   * arrive within 5 seconds: the button's plain link, as without JavaScript.
   */
  function openHtml(box, src, scripts, styles) {
    var html = null;
    var styled = false;
    var done = false;
    var pending = styles.length;

    function insert() {
      if (done || html === null || !styled) {
        return;
      }
      done = true;
      var holder = document.createElement('div');
      holder.className = 'shoji-markup';
      holder.setAttribute('tabindex', '-1');
      // Parsed as HTML; scripts in it stay inert. Values the site's snippet
      // filled in are kept as they are.
      holder.innerHTML = html;
      box.appendChild(holder);
      reveal(box, holder);
      emit(box, 'shoji:inserted');
      loadScripts(box, scripts, function () {
        emit(box, 'shoji:loaded');
      });
    }
    function styleDone() {
      if (--pending <= 0) {
        styled = true;
        insert();
      }
    }
    // The markup is not coming (error, or nothing within WAIT): the plain
    // link, as without JavaScript. Once the markup is in, or the link was
    // taken, a late answer or a late timer changes nothing.
    function fail() {
      if (done) {
        return;
      }
      done = true;
      box.removeAttribute('data-shoji-state');
      box.classList.remove('shoji-loading');
      var button = box.querySelector('a.shoji-button');
      if (button && button.href) {
        window.location.href = button.href;
      }
    }

    if (pending === 0) {
      styled = true;
    } else {
      window.setTimeout(function () {
        styled = true;
        insert();
      }, WAIT);
      for (var i = 0; i < styles.length; i++) {
        load('link', styles[i], styleDone);
      }
    }
    window.fetch(src, { credentials: 'same-origin', headers: { Accept: 'text/html' } })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }
        return response.text();
      })
      .then(function (text) {
        html = text;
        insert();
      }, fail);
    window.setTimeout(function () {
      if (html === null) {
        fail();
      }
    }, WAIT);
  }

  function open(box) {
    if (box.hasAttribute('data-shoji-state')) {
      return false;
    }
    var html = box.hasAttribute('data-shoji-html');
    var src = html
      ? own(box.getAttribute('data-shoji-html'), 'html')
      : own(box.getAttribute('data-shoji-frame'), 'frame');
    var scripts = ownList(box.getAttribute('data-shoji-script'), 'script');
    var styles = html ? ownList(box.getAttribute('data-shoji-style'), 'style') : [];
    if (!src || !scripts || !styles || (html && typeof window.fetch !== 'function')) {
      return false;
    }
    box.setAttribute('data-shoji-state', 'loading');
    box.classList.add('shoji-loading');
    emit(box, 'shoji:open');

    if (html) {
      openHtml(box, src, scripts, styles);
    } else {
      loadScripts(box, scripts, function () {
        insertFrame(box, src);
      });
    }
    return true;
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
    // Not opened (an address that is not Shoji's own): the button stays a
    // plain link to the site's /shoji/go/… address.
    if (open(box)) {
      event.preventDefault();
    }
  });
})();
