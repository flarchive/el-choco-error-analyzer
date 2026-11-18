const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { runInNewContext } = require('node:vm');
const { test } = require('node:test');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const mithril = require(path.join(root, 'js/node_modules/mithril'));

function setup() {
  const modules = {};
  const requests = [];
  const app = {
    forum: { attribute: (key) => { assert.equal(key, 'apiUrl'); return 'https://forum.test/sub/api'; } },
    translator: { trans: (key) => key.split('.').pop(), getLocale: () => 'de@formal' },
    request: async (options) => { requests.push(options); return structuredClone(fixture); },
  };
  class ExtensionPage { oninit() {} }
  class Admin { page(component) { this.component = component; return this; } }
  const m = (...args) => mithril(...args);
  m.redraw = () => {};
  const core = { 'admin/app': app, 'admin/components/ExtensionPage': ExtensionPage, 'common/extenders/Admin': Admin };
  const context = {
    m, module: { exports: {} }, navigator: {}, setTimeout, Blob, URL,
    flarum: { reg: { get: (_, name) => core[name], add: (_, name, value) => { modules[name] = value; } } },
  };
  runInNewContext(readFileSync(path.join(root, 'js/dist/admin.js'), 'utf8'), context);
  const page = new modules.admin.AnalyzerPage();
  page.oninit({ attrs: { id: 'el-choco-error-analyzer' } });
  return { page, app, modules, requests, context };
}

const group = { id: 'a', level: 'ERROR', count: 3, message: 'Broken image <script>throw 1</script>',
  first: '2026-11-18T10:00:00Z', last: '2026-11-18T11:00:00Z',
  sample: 'Error with ``` in the message\n#0 [flarum]/vendor/acme/media/A.php(12): run(…)',
  packages: [{ name: 'acme/media', version: '1.2.0', type: 'extension', enabled: true }], files: ['flarum-2026-11-18.log'] };
const fixture = {
  version: '0.1.0-alpha.2', generatedAt: '2026-11-18T12:00:00Z',
  scan: { filesScanned: 1, filesFound: 1, records: 6, matched: 4, bytes: 12345, partial: true, warnings: [] },
  environment: { flarum: 'v2.0.2', phpWeb: '8.4.26', extensions: [...group.packages, { name: 'acme/other', version: '2.0.0', enabled: false }] },
  groups: [group, { ...group, id: 'b', count: 1, message: 'Other failure', last: '2026-11-18T12:00:00Z', packages: [] }],
};

test('compiled Flarum 2 page registers and waits for an explicit scan', () => {
  const { page, requests, modules } = setup();
  assert.equal(modules.admin.extend.length, 1);
  assert.equal(modules.admin.extend[0].component, modules.admin.AnalyzerPage);
  assert.equal(requests.length, 0);
  assert.match(JSON.stringify(page.content()), /start_title/);
});

test('scan uses the actual API base URL and selected bounded options', async () => {
  const { page, requests } = setup();
  page.files = '10';
  page.minimum = 'ERROR';
  await page.scan();
  assert.equal(requests[0].method, 'GET');
  assert.equal(requests[0].url, 'https://forum.test/sub/api/error-analyzer');
  assert.equal(requests[0].params.files, '10');
  assert.equal(requests[0].params.minimum, 'ERROR');
  assert.equal(page.loading, false);
  assert.equal(page.groups()[0].id, 'b');
  assert.match(JSON.stringify(page.content()), /partial/);
});

test('search, package filtering and frequency sorting use the existing snapshot', async () => {
  const { page, requests } = setup();
  await page.scan();
  page.query = 'ACME/MEDIA';
  assert.equal(page.groups().length, 1);
  page.query = '';
  page.packageFilter = 'acme/media';
  assert.equal(page.groups()[0].id, 'a');
  page.packageFilter = '';
  page.sort = 'count';
  assert.equal(page.groups()[0].count, 3);
  assert.equal(requests.length, 1);
});

test('changing groups clears the previous report and notes', async () => {
  const { page } = setup();
  await page.scan();
  page.report = 'Previous report';
  page.notes = 'Previous notes';
  page.select('b');
  assert.equal(page.report, '');
  assert.equal(page.notes, '');
  page.query = 'no matches';
  assert.match(JSON.stringify(page.content()), /choose_group/);
});

test('failed scans clear loading state and allow retry', async () => {
  const { page, app } = setup();
  app.request = async () => { throw new Error('Offline'); };
  await page.scan();
  assert.equal(page.error, true);
  assert.equal(page.loading, false);
  assert.match(JSON.stringify(page.content()), /scan_failed/);
  app.request = async () => structuredClone(fixture);
  await page.scan();
  assert.equal(page.error, false);
});

test('a response received after leaving the page is discarded', async () => {
  const { page, app } = setup();
  let finish;
  app.request = () => new Promise((resolve) => { finish = resolve; });
  const pending = page.scan();
  page.onremove();
  finish(fixture);
  await pending;
  assert.equal(page.data, null);
});

test('reports distinguish web PHP and include the full extension list only on request', () => {
  const { modules } = setup();
  const make = modules.report.buildReport;
  const report = make(fixture, group, 'Steps to reproduce', false, (key) => key);
  assert.match(report, /PHP \(Web\) 8\.4\.26/);
  assert.match(report, /Steps to reproduce/);
  assert.match(report, /report_partial/);
  assert.match(report, /````text/);
  assert.doesNotMatch(report, /acme\/other/);
  const complete = make(fixture, group, '', true, (key) => key);
  assert.match(complete, /acme\/other 2\.0\.0 \(disabled\)/);
  assert.match(complete, /report_problem_placeholder/);
});

test('clipboard success and manual fallback use the edited preview text', async () => {
  const { page, context } = setup();
  page.report = 'Reviewed report';
  await page.copyReport();
  assert.equal(page.copyState, 'copy_manual');
  context.navigator.clipboard = { writeText: async (value) => { assert.equal(value, 'Reviewed report'); } };
  await page.copyReport();
  assert.equal(page.copyState, 'copied');
});

test('prefix and path evidence are shown in details and exported reports', async () => {
  const { page, modules } = setup();
  await page.scan();
  const depot = { name: 'ernestdefoe/depot', version: 'v1.5.2', type: 'extension', enabled: true, evidence: ['prefix'], logPrefix: '[depot]' };
  const depotGroup = { ...group, message: '[depot] GitHub sync skipped', packages: [depot] };
  const details = JSON.stringify(page.details(depotGroup));
  assert.match(details, /evidence_prefix/);
  assert.match(details, /\[depot\]/);
  const report = modules.report.buildReport(fixture, depotGroup, '', false, (key) => key);
  assert.match(report, /ernestdefoe\/depot v1\.5\.2/);
  assert.match(report, /evidence_prefix \[depot\]/);
  assert.doesNotMatch(report, /evidence_path/);
  const both = modules.report.attributionText({ ...depot, evidence: ['path', 'prefix'] }, (key) => key);
  assert.equal(both, 'evidence_path · evidence_prefix [depot]');
  assert.equal(modules.report.attributionText(group.packages[0], (key) => key), 'evidence_path');
  page.packageFilter = 'ernestdefoe/depot';
  page.data.groups = [depotGroup];
  assert.equal(page.groups().length, 1);
});

test('source renders log text through Mithril text nodes', () => {
  const source = readFileSync(path.join(root, 'js/src/admin.js'), 'utf8');
  assert.doesNotMatch(source, /m\.trust|innerHTML|outerHTML|eval\(/);
});
