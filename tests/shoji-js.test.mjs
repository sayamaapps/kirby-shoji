// shoji.js against a minimal fake DOM (no dependencies): it opens only its
// own /shoji/ addresses, never a javascript: frame or a foreign script.
//   node --test tests/
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../assets/shoji.js', import.meta.url), 'utf8');

function setup(attributes) {
  const listeners = {};
  const appended = [];
  const events = [];
  const make = (tag, attrs = {}) => {
    const el = {
      tagName: tag.toUpperCase(), attrs: { ...attrs }, children: [], classes: new Set(), listeners: {},
      getAttribute: (n) => (n in el.attrs ? el.attrs[n] : null),
      setAttribute: (n, v) => { el.attrs[n] = String(v); },
      hasAttribute: (n) => n in el.attrs,
      classList: { add: (c) => el.classes.add(c), remove: (c) => el.classes.delete(c), contains: (c) => el.classes.has(c) },
      appendChild: (child) => { el.children.push(child); appended.push(child); },
      removeChild: (child) => { el.children = el.children.filter((c) => c !== child); },
      contains: () => false,
      addEventListener: (type, fn) => { el.listeners[type] = fn; },
      dispatchEvent: (e) => events.push(e.type),
      focus() {},
    };
    return el;
  };
  const box = make('div', { 'data-shoji': '', ...attributes });
  const button = make('a', { class: 'shoji-button' });
  button.classList.add('shoji-button');
  button.closest = (sel) => (sel === 'a' ? button : box);
  const document = {
    head: make('head'),
    activeElement: null,
    addEventListener: (type, fn) => { listeners[type] = fn; },
    createElement: (tag) => make(tag),
  };
  const window = { location: new URL('https://site.example/page'), setTimeout: () => 0, CustomEvent: class { constructor(type) { this.type = type; } } };
  vm.runInNewContext(source, { window, document, URL, CustomEvent: window.CustomEvent, Array });
  const click = () => {
    let prevented = false;
    listeners.click({ target: button, button: 0, preventDefault: () => { prevented = true; } });
    return prevented;
  };
  return { click, appended, events, box };
}

test('opens its own frame address', () => {
  const { click, appended, events } = setup({ 'data-shoji-frame': 'https://site.example/shoji/frame/youtube/dQw4w9WgXcQ?s=abc' });
  assert.equal(click(), true, 'default prevented');
  assert.equal(appended.length, 1);
  assert.equal(appended[0].tagName, 'IFRAME');
  assert.equal(appended[0].src, 'https://site.example/shoji/frame/youtube/dQw4w9WgXcQ?s=abc');
  assert.deepEqual(events, ['shoji:open']);
});

test('works under a subfolder', () => {
  const { appended, click } = setup({ 'data-shoji-frame': 'https://site.example/sub/shoji/frame/vimeo/76979871?s=abc' });
  click();
  assert.equal(appended[0].src, 'https://site.example/sub/shoji/frame/vimeo/76979871?s=abc');
});

for (const [name, frame] of [
  ['javascript: frame', 'javascript:alert(1)'],
  ['foreign host', 'https://evil.example/shoji/frame/youtube/dQw4w9WgXcQ'],
  ['not a shoji address', 'https://site.example/panel'],
  ['data: frame', 'data:text/html,<script>alert(1)</script>'],
]) {
  test(`refuses a ${name}`, () => {
    const { click, appended } = setup({ 'data-shoji-frame': frame });
    assert.equal(click(), false, 'link left alone');
    assert.equal(appended.length, 0, 'nothing inserted');
  });
}

test('refuses a foreign script, and then opens nothing at all', () => {
  const { click, appended } = setup({
    'data-shoji-frame': 'https://site.example/shoji/frame/digistore/123456?s=abc',
    'data-shoji-script': 'https://evil.example/x.js',
  });
  assert.equal(click(), false);
  assert.equal(appended.length, 0);
});

test('loads its own script first, then the frame', () => {
  const { click, appended } = setup({
    'data-shoji-frame': 'https://site.example/shoji/frame/digistore/123456?s=abc',
    'data-shoji-script': 'https://site.example/shoji/script/digistore/123456?s=def',
  });
  assert.equal(click(), true);
  assert.equal(appended[0].tagName, 'SCRIPT');
  assert.equal(appended[0].src, 'https://site.example/shoji/script/digistore/123456?s=def');
  appended[0].onload();
  assert.equal(appended[1].tagName, 'IFRAME');
});
