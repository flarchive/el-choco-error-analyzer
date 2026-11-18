import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Admin from 'flarum/common/extenders/Admin';
import { attributionText, buildReport } from './report';

const translate = (key, values = {}) => app.translator.trans('el-choco-error-analyzer.admin.' + key, values);
const reportText = (key) => {
  const value = translate(key);
  return Array.isArray(value) ? value.join('') : String(value);
};
const icon = (name) => m('i', { class: 'fas fa-' + name, 'aria-hidden': 'true' });

export class AnalyzerPage extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);
    this.data = null;
    this.loading = false;
    this.error = false;
    this.query = '';
    this.packageFilter = '';
    this.sort = 'latest';
    this.minimum = 'WARNING';
    this.files = '5';
    this.selected = null;
    this.notes = '';
    this.report = '';
    this.includeExtensions = false;
    this.copyState = '';
    this.removed = false;
  }

  onremove() {
    this.removed = true;
  }

  async scan() {
    if (this.loading) return;
    this.loading = true;
    this.error = false;
    this.copyState = '';
    try {
      const data = await app.request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/error-analyzer',
        params: { files: this.files, minimum: this.minimum },
        errorHandler: () => {},
      });
      if (this.removed) return;
      this.data = data;
      this.selected = data.groups[0]?.id || null;
      this.report = '';
      this.notes = '';
    } catch (error) {
      if (!this.removed) this.error = true;
    } finally {
      if (!this.removed) {
        this.loading = false;
        m.redraw();
      }
    }
  }

  groups() {
    const query = this.query.toLocaleLowerCase();
    return (this.data?.groups || []).filter((group) => {
      const packageMatches = !this.packageFilter || group.packages.some((item) => item.name === this.packageFilter);
      const searchable = [group.message, group.level, ...group.packages.map((item) => item.name)].join(' ').toLocaleLowerCase();
      return packageMatches && searchable.includes(query);
    }).sort((left, right) => this.sort === 'count' ? right.count - left.count || right.last.localeCompare(left.last) : right.last.localeCompare(left.last));
  }

  select(id) {
    this.selected = id;
    this.notes = '';
    this.report = '';
    this.copyState = '';
  }

  date(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return translate('unknown');
    return date.toLocaleString(app.translator.getLocale().replace('@formal', '').replace('_', '-'));
  }

  async copyReport() {
    try {
      await navigator.clipboard.writeText(this.report);
      this.copyState = 'copied';
    } catch (error) {
      this.copyState = 'copy_manual';
    }
    if (!this.removed) m.redraw();
  }

  downloadReport() {
    const url = URL.createObjectURL(new Blob([this.report], { type: 'text/plain;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = 'error-analyzer-report.txt';
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  content() {
    const visible = this.groups();
    const selected = visible.find((group) => group.id === this.selected);
    return m('.EA', [
      m('section.EA-hero', [
        m('div', [m('h2', [icon('stethoscope'), ' Error Analyzer']), m('p', translate('intro'))]),
        m('span.EA-version', this.data?.version || '0.1.0-alpha.2'),
      ]),
      m('section.EA-scan', { 'aria-label': reportText('scan_options') }, [
        m('label', [translate('file_limit'), m('select.FormControl', { value: this.files, disabled: this.loading, onchange: (event) => { this.files = event.target.value; } },
          [5, 10, 20].map((count) => m('option', { value: count }, translate('latest_files', { count }))))]),
        m('label', [translate('minimum'), m('select.FormControl', { value: this.minimum, disabled: this.loading, onchange: (event) => { this.minimum = event.target.value; } },
          ['WARNING', 'ERROR', 'DEBUG'].map((level) => m('option', { value: level }, translate('minimum_' + level.toLowerCase()))))]),
        m('button.Button.Button--primary', { type: 'button', disabled: this.loading, onclick: () => this.scan() },
          [icon(this.loading ? 'circle-notch fa-spin' : 'magnifying-glass'), ' ', translate(this.loading ? 'scanning' : 'scan')]),
      ]),
      this.error && m('.Alert.Alert--error', { role: 'alert' }, translate('scan_failed')),
      this.loading && m('p.EA-status', { role: 'status' }, translate('scanning_help')),
      !this.data && !this.loading && m('.EA-empty', [icon('file-lines'), m('h3', translate('start_title')), m('p', translate('start_help'))]),
      this.data && this.results(visible, selected),
    ]);
  }

  results(visible, selected) {
    const packages = [...new Set(this.data.groups.flatMap((group) => group.packages.map((item) => item.name)))].sort();
    return m('div', [
      m('.EA-metrics', [
        [this.data.groups.length, 'groups'], [this.data.scan.matched, 'occurrences'], [this.data.scan.filesScanned, 'files_scanned'],
      ].map(([value, label]) => m('div', [m('strong', value), m('span', translate(label))]))),
      m('p.EA-scope', [translate('scope', { count: this.data.scan.records, bytes: (this.data.scan.bytes / 1048576).toFixed(1) }),
        ' · ', translate('scanned_at'), ' ', this.date(this.data.generatedAt)]),
      this.data.scan.partial && m('.Alert.Alert--warning', translate('partial')),
      ...this.data.scan.warnings.map((warning) => m('.Alert.Alert--warning', translate('warnings.' + warning))),
      m('.EA-filters', [
        m('label', [translate('search'), m('input.FormControl', { type: 'search', value: this.query, placeholder: reportText('search_placeholder'), oninput: (event) => { this.query = event.target.value; } })]),
        m('label', [translate('package_filter'), m('select.FormControl', { value: this.packageFilter, onchange: (event) => { this.packageFilter = event.target.value; } },
          [m('option', { value: '' }, translate('all_packages')), ...packages.map((name) => m('option', { value: name }, name))])]),
        m('label', [translate('sort'), m('select.FormControl', { value: this.sort, onchange: (event) => { this.sort = event.target.value; } },
          ['latest', 'count'].map((value) => m('option', { value }, translate('sort_' + value))))]),
      ]),
      this.data.groups.length === 0 ? m('.EA-empty', [m('h3', translate('no_errors')), m('p', translate('no_errors_help'))]) :
        m('.EA-layout', [
          m('section.EA-list', { 'aria-label': reportText('groups') }, [
            !visible.length && m('p.EA-empty', translate('no_matches')),
            ...visible.map((group) => m('button.EA-group', {
              type: 'button', className: group.id === this.selected ? 'is-selected' : '',
              'aria-pressed': group.id === this.selected, onclick: () => this.select(group.id),
            }, [
              m('.EA-group-top', [m('span.EA-level', { className: 'EA-level--' + group.level.toLowerCase() }, group.level), m('strong', '×' + group.count)]),
              m('span.EA-message', group.message || translate('unknown')),
              m('small', this.date(group.last)),
              m('span.EA-package-preview', group.packages.filter((item) => item.type === 'extension').map((item) => item.name).join(' · ') || translate('no_extension')),
            ])),
          ]),
          m('section.EA-detail', { 'aria-label': reportText('details') }, selected ? this.details(selected) : m('p.EA-empty', translate('choose_group'))),
        ]),
    ]);
  }

  details(group) {
    return [
      m('h3', translate('details')),
      m('p.EA-detail-message', group.message),
      m('dl.EA-times', [m('dt', translate('first')), m('dd', this.date(group.first)), m('dt', translate('last')), m('dd', this.date(group.last))]),
      m('h4', translate('packages')),
      m('p.EA-muted', translate('attribution_help')),
      group.packages.length ? m('ul.EA-packages', group.packages.map((item) => m('li', [
        m('div', [m('code', item.name), m('small', translate('type_' + item.type)),
          m('small.EA-evidence', attributionText(item, reportText))]), m('span', item.version),
      ]))) : m('p.EA-muted', translate('no_packages')),
      m('details.EA-log', { open: true }, [m('summary', translate('sample')), m('pre', group.sample)]),
      m('p.EA-muted', [translate('log_files'), ' ', group.files.join(', ')]),
      m('.EA-report-builder', [
        m('h4', translate('support_report')),
        m('label', [translate('problem'), m('textarea.FormControl', {
          rows: 3, value: this.notes, placeholder: reportText('problem_placeholder'), oninput: (event) => { this.notes = event.target.value; },
        })]),
        m('label.EA-check', [m('input', { type: 'checkbox', checked: this.includeExtensions, onchange: (event) => { this.includeExtensions = event.target.checked; } }), translate('include_extensions')]),
        m('button.Button.Button--primary', { type: 'button', onclick: () => {
          this.report = buildReport(this.data, group, this.notes, this.includeExtensions, reportText);
          this.copyState = '';
        } }, [icon('file-export'), ' ', translate('prepare_report')]),
        this.report && m('.EA-report-preview', [
          m('p.Alert.Alert--warning', translate('review_report')),
          m('label', [translate('editable_report'), m('textarea.FormControl.EA-report', {
            rows: 16, value: this.report, spellcheck: false, oninput: (event) => { this.report = event.target.value; this.copyState = ''; },
          })]),
          m('.EA-report-actions', [
            m('button.Button.Button--primary', { type: 'button', onclick: () => this.copyReport() }, [icon('copy'), ' ', translate('copy_report')]),
            m('button.Button', { type: 'button', onclick: () => this.downloadReport() }, [icon('download'), ' ', translate('download_report')]),
          ]),
          this.copyState && m('p', { role: 'status' }, translate(this.copyState)),
        ]),
      ]),
    ];
  }
}

export const extend = [new Admin().page(AnalyzerPage)];
