// shoji.js against a minimal fake DOM (no dependencies): it opens only its
// own /shoji/ addresses, never a javascript: frame or a foreign script; kind
// html inserts the site's markup, then fires shoji:inserted, then loads the
// provider scripts.
//   node --test tests/
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../assets/shoji.js', import.meta.url), 'utf8');

function setup(attributes, { fetch } = {}) {
  const listeners = {};
  const appended = [];
  const events = [];
  const timers = [];
  const fetched = [];
  const make = (tag, attrs = {}) => {
    const el = {
      tagName: tag.toUpperCase(), attrs: { ...attrs }, children: [], classes: new Set(), listeners: {},
      getAttribute: (n) => (n in el.attrs ? el.attrs[n] : null),
      setAttribute: (n, v) => { el.attrs[n] = String(v); },
      hasAttribute: (n) => n in el.attrs,
      removeAttribute: (n) => { delete el.attrs[n]; },
      querySelector: (sel) => (sel === 'a.shoji-button' ? el.children.find((c) => c.tagName === 'A') ?? null : null),
      classList: { add: (c) => el.classes.add(c), remove: (c) => el.classes.delete(c), contains: (c) => el.classes.has(c) },
      appendChild: (child) => { el.children.push(child); appended.push(child); },
      removeChild: (child) => { el.children = el.children.filter((c) => c !== child); },
      contains: () => false,
      addEventListener: (type, fn) => { el.listeners[type] = fn; },
      dispatchEvent: (e) => events.push(e.type + (e.type === 'shoji:inserted' ? ':' + box.children.map((c) => c.className ?? c.tagName).join(',') : '')),
      focus() { el.focused = true; },
    };
    return el;
  };
  const box = make('div', { 'data-shoji': '', ...attributes });
  const button = make('a', { class: 'shoji-button' });
  button.classList.add('shoji-button');
  button.href = 'https://site.example/shoji/go/x/y?s=link';
  box.children.push(button);
  button.closest = (sel) => (sel === 'a' ? button : box);
  const document = {
    head: make('head'),
    activeElement: null,
    addEventListener: (type, fn) => { listeners[type] = fn; },
    createElement: (tag) => make(tag),
  };
  const window = {
    location: new URL('https://site.example/page'),
    setTimeout: (fn) => { timers.push(fn); return 0; },
    CustomEvent: class { constructor(type) { this.type = type; } },
  };
  if (fetch) {
    window.fetch = (url, options) => { fetched.push({ url, options }); return fetch(url); };
  }
  vm.runInNewContext(source, { window, document, URL, CustomEvent: window.CustomEvent, Array });
  const click = () => {
    let prevented = false;
    listeners.click({ target: button, button: 0, preventDefault: () => { prevented = true; } });
    return prevented;
  };
  const settle = () => new Promise((resolve) => setImmediate(resolve));
  return { click, appended, events, box, timers, fetched, settle, window };
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

// Kind html: the site's markup (a Brevo form with a hidden campaign field)
const markup = '<form action="https://abc123.sibforms.com/serve/X"><input type="hidden" name="CAM" value="from-server"><input name="EMAIL"></form>';
const ok = (text) => () => Promise.resolve({ ok: true, status: 200, text: () => Promise.resolve(text) });
const brevo = {
  'data-shoji-provider': 'brevo',
  'data-shoji-html': 'https://site.example/shoji/html/brevo/newsletter?p=cam%3Dx&s=abc',
  'data-shoji-script': 'https://site.example/shoji/script/brevo/newsletter?s=a https://site.example/shoji/script/brevo/newsletter?n=1&s=b',
  'data-shoji-style': 'https://site.example/shoji/style/brevo/newsletter?s=c',
};

test('html: fetches its own markup address, nothing else before the stylesheet and the markup are there', async () => {
  const { click, appended, fetched, events, settle } = setup(brevo, { fetch: ok(markup) });
  assert.equal(click(), true, 'default prevented');
  assert.equal(fetched.length, 1);
  assert.equal(fetched[0].url, 'https://site.example/shoji/html/brevo/newsletter?p=cam%3Dx&s=abc');
  assert.equal(fetched[0].options.credentials, 'same-origin');
  assert.deepEqual(appended.map((e) => e.tagName), ['LINK'], 'only the stylesheet so far');
  assert.equal(appended[0].rel, 'stylesheet');
  assert.equal(appended[0].href, 'https://site.example/shoji/style/brevo/newsletter?s=c');
  await settle();
  assert.deepEqual(events, ['shoji:open'], 'markup waits for the stylesheet');
  assert.equal(appended.some((e) => e.tagName === 'SCRIPT'), false, 'no provider script before the markup is in the page');
});

test('html: markup in, placeholder gone, shoji:inserted, then the scripts in order, then shoji:loaded', async () => {
  const { click, appended, events, box, settle } = setup(brevo, { fetch: ok(markup) });
  click();
  appended[0].onload(); // stylesheet
  await settle();
  const holder = box.children[0];
  assert.equal(box.children.length, 1, 'placeholder content removed');
  assert.equal(holder.className, 'shoji-markup');
  assert.equal(holder.innerHTML, markup, 'markup inserted as served: the server-filled hidden field is kept');
  assert.equal(box.classes.has('shoji-opened'), true);
  assert.equal(box.classes.has('shoji-loading'), false);
  assert.equal(box.attrs['data-shoji-state'], 'opened');
  assert.deepEqual(events, ['shoji:open', 'shoji:inserted:shoji-markup'], 'shoji:inserted fires with the markup already in the box');
  const scripts = appended.filter((e) => e.tagName === 'SCRIPT');
  assert.deepEqual(scripts.map((e) => e.src), ['https://site.example/shoji/script/brevo/newsletter?s=a'], 'first script only');
  scripts[0].onload();
  const second = appended.filter((e) => e.tagName === 'SCRIPT')[1];
  assert.equal(second.src, 'https://site.example/shoji/script/brevo/newsletter?n=1&s=b', 'second script after the first');
  assert.deepEqual(events.slice(2), [], 'not loaded before the last script');
  second.onerror(); // a failed script does not block
  assert.deepEqual(events, ['shoji:open', 'shoji:inserted:shoji-markup', 'shoji:script', 'shoji:loaded']);
});

test('html: a stylesheet that never answers holds the markup back 5 seconds at most', async () => {
  const { click, events, timers, settle } = setup(brevo, { fetch: ok(markup) });
  click();
  await settle();
  assert.deepEqual(events, ['shoji:open']);
  timers.forEach((fn) => fn());
  assert.equal(events[1], 'shoji:inserted:shoji-markup');
});

test('html: markup that cannot be fetched follows the plain link, as without JavaScript', async () => {
  const { click, box, window, settle, events } = setup({ ...brevo, 'data-shoji-style': '' }, { fetch: () => Promise.resolve({ ok: false, status: 404 }) });
  assert.equal(click(), true);
  await settle();
  assert.equal(window.location.href, 'https://site.example/shoji/go/x/y?s=link');
  assert.equal(box.hasAttribute('data-shoji-state'), false, 'placeholder usable again');
  assert.equal(box.classes.has('shoji-loading'), false);
  assert.deepEqual(events, ['shoji:open']);
});

for (const [name, attributes] of [
  ['foreign markup address', { 'data-shoji-html': 'https://evil.example/shoji/html/brevo/newsletter?s=abc' }],
  ['markup address of another kind', { 'data-shoji-html': 'https://site.example/shoji/frame/brevo/newsletter?s=abc' }],
  ['javascript: markup address', { 'data-shoji-html': 'javascript:alert(1)' }],
  ['foreign script among own ones', { 'data-shoji-script': 'https://site.example/shoji/script/brevo/newsletter?s=a https://evil.example/x.js' }],
  ['foreign stylesheet', { 'data-shoji-style': 'https://evil.example/x.css' }],
  ['stylesheet given as a script address', { 'data-shoji-style': 'https://site.example/shoji/script/brevo/newsletter?s=a' }],
]) {
  test(`html: refuses a ${name}, and then loads nothing at all`, async () => {
    const { click, appended, fetched, settle } = setup({ ...brevo, ...attributes }, { fetch: ok(markup) });
    assert.equal(click(), false, 'link left alone');
    await settle();
    assert.equal(fetched.length, 0, 'nothing fetched');
    assert.equal(appended.length, 0, 'nothing inserted');
  });
}

test('html: a browser without fetch keeps the plain link', () => {
  const { click, appended } = setup(brevo);
  assert.equal(click(), false);
  assert.equal(appended.length, 0);
});

test('iframe kinds: several own scripts load in order before the frame', () => {
  const { click, appended, events } = setup({
    'data-shoji-frame': 'https://site.example/shoji/frame/digistore/123456?s=abc',
    'data-shoji-script': 'https://site.example/shoji/script/digistore/123456?s=d https://site.example/shoji/script/digistore/123456?n=1&s=e',
  });
  click();
  appended[0].onload();
  assert.equal(appended[1].src, 'https://site.example/shoji/script/digistore/123456?n=1&s=e');
  appended[1].onload();
  assert.equal(appended[2].tagName, 'IFRAME');
  assert.deepEqual(events, ['shoji:open', 'shoji:script']);
});

test('html: the focus moves from the removed button to the inserted markup', async () => {
  const { click, box, settle } = setup({ ...brevo, 'data-shoji-style': '' }, { fetch: ok(markup) });
  box.contains = () => true; // the button had the focus
  click();
  await settle();
  const holder = box.children[0];
  assert.equal(holder.attrs.tabindex, '-1', 'focusable by script, not in the tab order');
  assert.equal(holder.focused, true);
});

// A step that never answers holds the content back for 5 seconds at most.
const digistore = {
  'data-shoji-frame': 'https://site.example/shoji/frame/digistore/123456?s=abc',
  'data-shoji-script': 'https://site.example/shoji/script/digistore/123456?s=d https://site.example/shoji/script/digistore/123456?n=1&s=e',
};
/** Fires every timer registered so far (once), like 5 seconds passing. */
const elapse = (timers) => timers.splice(0).forEach((fn) => fn());
const tags = (appended) => appended.map((e) => e.tagName);

test('iframe: a script that never answers holds the frame back 5 seconds, then the frame opens', () => {
  const { click, appended, events, timers, box } = setup(digistore);
  assert.equal(click(), true);
  assert.deepEqual(tags(appended), ['SCRIPT'], 'waiting for the first script');
  assert.equal(box.attrs['data-shoji-state'], 'loading');
  assert.equal(box.classes.has('shoji-loading'), true);
  elapse(timers); // 5 seconds, the script is still pending
  assert.deepEqual(tags(appended), ['SCRIPT', 'IFRAME'], 'the frame opens without the script');
  assert.equal(appended[1].src, 'https://site.example/shoji/frame/digistore/123456?s=abc');
  assert.deepEqual(events, ['shoji:open', 'shoji:script'], 'the script step ends, announced once');
  elapse(timers); // the frame never fires load either: after 5 more seconds the placeholder goes
  assert.equal(box.attrs['data-shoji-state'], 'opened', 'the state is resolved, not stuck on loading');
  assert.equal(box.classes.has('shoji-loading'), false);
  assert.equal(box.classes.has('shoji-opened'), true);
  assert.deepEqual(events, ['shoji:open', 'shoji:script', 'shoji:loaded']);
});

test('iframe: a script that arrives after the timeout opens nothing twice, the next script still follows', () => {
  const { click, appended, events, timers } = setup(digistore);
  click();
  elapse(timers);
  assert.deepEqual(tags(appended), ['SCRIPT', 'IFRAME']);
  appended[0].onload(); // late
  assert.deepEqual(tags(appended), ['SCRIPT', 'IFRAME', 'SCRIPT'], 'no second frame; the second script loads in its order');
  assert.equal(appended[2].src, 'https://site.example/shoji/script/digistore/123456?n=1&s=e');
  appended[2].onload();
  elapse(timers);
  assert.deepEqual(tags(appended), ['SCRIPT', 'IFRAME', 'SCRIPT'], 'still one frame');
  assert.deepEqual(events, ['shoji:open', 'shoji:script', 'shoji:loaded'], 'each event once');
});

test('iframe: scripts that finish in time are not announced a second time by the timer', () => {
  const { click, appended, events, timers } = setup(digistore);
  click();
  appended[0].onload();
  appended[1].onerror();
  assert.deepEqual(tags(appended), ['SCRIPT', 'SCRIPT', 'IFRAME']);
  elapse(timers);
  assert.deepEqual(tags(appended), ['SCRIPT', 'SCRIPT', 'IFRAME'], 'the timer opens no second frame');
  assert.deepEqual(events, ['shoji:open', 'shoji:script', 'shoji:loaded']);
});

test('iframe: without provider scripts the frame opens at once, no script timer', () => {
  const { click, appended, timers } = setup({ 'data-shoji-frame': 'https://site.example/shoji/frame/youtube/dQw4w9WgXcQ?s=abc' });
  click();
  assert.deepEqual(tags(appended), ['IFRAME']);
  assert.equal(timers.length, 1, 'only the frame\'s own timer');
});

test('html: scripts that never answer do not hold shoji:loaded back; late ones change nothing', async () => {
  const { click, appended, events, timers, box, settle } = setup({ ...brevo, 'data-shoji-style': '' }, { fetch: ok(markup) });
  click();
  await settle();
  assert.deepEqual(events, ['shoji:open', 'shoji:inserted:shoji-markup'], 'markup is in, the first script is pending');
  assert.equal(box.attrs['data-shoji-state'], 'opened');
  elapse(timers);
  assert.deepEqual(events, ['shoji:open', 'shoji:inserted:shoji-markup', 'shoji:script', 'shoji:loaded']);
  const scripts = appended.filter((e) => e.tagName === 'SCRIPT');
  scripts[0].onload(); // late: runs, the next one follows
  const later = appended.filter((e) => e.tagName === 'SCRIPT');
  assert.equal(later.length, 2);
  later[1].onload();
  elapse(timers);
  assert.equal(events.length, 4, 'nothing announced twice');
});

test('html: markup that does not arrive within 5 seconds follows the plain link, a late answer changes nothing', async () => {
  let answer;
  const { click, box, window, settle, events, timers } = setup({ ...brevo, 'data-shoji-style': '' }, { fetch: () => new Promise((resolve) => { answer = resolve; }) });
  assert.equal(click(), true);
  await settle();
  assert.equal(box.attrs['data-shoji-state'], 'loading', 'waiting');
  assert.equal(window.location.href, 'https://site.example/page', 'not left yet');
  elapse(timers);
  assert.equal(window.location.href, 'https://site.example/shoji/go/x/y?s=link', 'the plain link, as for an error');
  assert.equal(box.hasAttribute('data-shoji-state'), false, 'placeholder usable again');
  assert.equal(box.classes.has('shoji-loading'), false);
  answer({ ok: true, status: 200, text: () => Promise.resolve(markup) }); // too late
  await settle();
  assert.equal(box.children.length, 1, 'no markup inserted afterwards');
  assert.deepEqual(events, ['shoji:open']);
});

test('html: markup that arrives in time is not disturbed by the timer', async () => {
  const { click, box, window, settle, events, timers } = setup({ ...brevo, 'data-shoji-style': '', 'data-shoji-script': '' }, { fetch: ok(markup) });
  click();
  await settle();
  assert.equal(box.attrs['data-shoji-state'], 'opened');
  elapse(timers);
  assert.equal(window.location.href, 'https://site.example/page', 'not left');
  assert.equal(box.attrs['data-shoji-state'], 'opened');
  assert.deepEqual(events, ['shoji:open', 'shoji:inserted:shoji-markup', 'shoji:loaded']);
});

test('html: a late error of an attempt that timed out does not reset the next attempt', async () => {
  const pending = [];
  const { click, box, settle, timers } = setup({ ...brevo, 'data-shoji-style': '' }, { fetch: () => new Promise((resolve, reject) => pending.push({ resolve, reject })) });
  click();
  await settle();
  elapse(timers);
  assert.equal(box.hasAttribute('data-shoji-state'), false, 'first attempt given up');
  click(); // the visitor tries again
  assert.equal(box.attrs['data-shoji-state'], 'loading');
  pending[0].reject(new Error('late'));
  await settle();
  assert.equal(box.attrs['data-shoji-state'], 'loading', 'the second attempt is left alone');
  assert.equal(box.classes.has('shoji-loading'), true);
});
