#!/usr/bin/env node
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '..', '..');
const page = fs.readFileSync(path.join(root, 'desktop/php/Palazzetti.php'), 'utf8');
const normalized = page.replaceAll('\\"', '"');
const js = fs.readFileSync(path.join(root, 'desktop/js/community.js'), 'utf8');
assert.equal((normalized.match(/data-action="createCommunityPost"/g) || []).length, 1);
assert.match(normalized, /data-plugin_id="Palazzetti"/);
assert.match(normalized, /role="button"/);
assert.match(normalized, /tabindex="0"/);
assert.match(page, /4\.4\.0/);
assert.match(page, /include_file\('desktop', 'community', 'js'/);


function harness(api = true, confirmResult = true, useDialog = false, includeOptional = true) {
  const handlers = {};
  const documentHandlers = {};
  const calls = [];
  const alerts = [];
  const opened = [];
  const previews = [];
  const attrs = {'data-plugin_id': 'Palazzetti'};
  const button = {
    dataset: {}, hidden: false,
    classList: {toggle() {}},
    setAttribute(name, value) { attrs[name] = String(value); },
    getAttribute(name) { return attrs[name] || null; },
    addEventListener(name, callback) { handlers[name] = callback; }
  };
  const document = {
    body: {appendChild(link) { opened.push(link); }},
    querySelector() { return button; },
    getElementById() { return null; },
    addEventListener(name, callback) { documentHandlers[name] = callback; },
    removeEventListener(name, callback) {
      if (documentHandlers[name] === callback) delete documentHandlers[name];
    },
    createElement(name) {
      if (name === 'pre') {
        return {
          className: '', textContent: '',
          get outerHTML() {
            return '<pre>' + this.textContent.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;') + '</pre>';
          }
        };
      }
      return {click() { this.clicked = true; }, remove() { this.removed = true; }};
    }
  };
  const jeedom = {plugin: {}};
  if (api) jeedom.plugin.createCommunityPost = params => calls.push(params);
  const context = {
    document, jeedom,
    jeedomUtils: {showAlert: alert => alerts.push(alert)},
    confirm(message) { previews.push(message); return confirmResult; },
    URL
  };
  if (useDialog) {
    context.jeeDialog = {
      confirm(message, callback) {
        previews.push(message);
        const checkbox = {id: 'communityIncludeOptionalDiagnostic', checked: includeOptional};
        if (documentHandlers.change) documentHandlers.change({target: checkbox});
        // Jeedom removes the dialog before invoking the callback.
        callback(confirmResult);
      }
    };
  }
  vm.runInNewContext(js, context);
  return {handlers, documentHandlers, calls, alerts, opened, button, attrs, previews};
}

const body = 'Core line\n<!-- JEEDOM_PLUGIN_DIAGNOSTICS_START -->\nOptional diagnostic\n<!-- JEEDOM_PLUGIN_DIAGNOSTICS_END -->';
const url = 'https://community.jeedom.com/new-topic?body=' + encodeURIComponent(body) + '&tags=plugin-Palazzetti';
const h = harness();
h.handlers.click({preventDefault() {}, stopPropagation() {}});
assert.equal(h.calls[0].type, 'Palazzetti');
h.handlers.click({preventDefault() {}, stopPropagation() {}});
assert.equal(h.calls.length, 1);
h.calls[0].success({url});
assert.equal(h.opened[0].href, url);
assert.equal(h.opened[0].rel, 'noopener noreferrer');
h.handlers.click({preventDefault() {}, stopPropagation() {}});
h.calls[1].error({message: 'unavailable'});
assert.equal(h.alerts.at(-1).level, 'danger');
h.handlers.keydown({key: 'Enter', preventDefault() {}, stopPropagation() {}});
assert.equal(h.calls.length, 3);

const cancel = harness(true, false);
cancel.handlers.click({preventDefault() {}, stopPropagation() {}});
cancel.calls[0].success({url});
assert.equal(cancel.opened.length, 0);
assert.equal(cancel.button.dataset.communityBusy, '0');

const remove = harness(true, true, true, false);
remove.handlers.click({preventDefault() {}, stopPropagation() {}});
remove.calls[0].success({url});
assert.equal(remove.opened.length, 1);
const filtered = new URL(remove.opened[0].href).searchParams.get('body');
assert.match(filtered, /Core line/);
assert.doesNotMatch(filtered, /Optional diagnostic|JEEDOM_PLUGIN_DIAGNOSTICS/);
assert.match(remove.previews[0], /Inclure le diagnostic facultatif/);
assert.doesNotMatch(remove.previews[0], /JEEDOM_PLUGIN_DIAGNOSTICS/);
assert.equal(remove.documentHandlers.change, undefined);

const keep = harness(true, true, true, true);
keep.handlers.click({preventDefault() {}, stopPropagation() {}});
keep.calls[0].success({url});
const retained = new URL(keep.opened[0].href).searchParams.get('body');
assert.match(retained, /Optional diagnostic/);
assert.equal(keep.documentHandlers.change, undefined);

const bad = harness(false);
assert.equal(bad.button.hidden, true);
assert.equal(bad.handlers.click, undefined);
console.log('Community UI tests: OK (Palazzetti)');
