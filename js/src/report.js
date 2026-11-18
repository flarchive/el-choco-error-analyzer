export function attributionText(item, text) {
  return (item.evidence || ['path']).map((source) =>
    text('evidence_' + source) + (source === 'prefix' && item.logPrefix ? ' ' + item.logPrefix : '')
  ).join(' · ');
}

export function buildReport(data, group, notes, includeExtensions, text) {
  const lines = [
    '# Error Analyzer · ' + data.version,
    '',
    text('report_generated') + ' ' + data.generatedAt,
    text('report_environment') + ' Flarum ' + data.environment.flarum + ' · PHP (Web) ' + data.environment.phpWeb,
    text('report_scope') + ' ' + data.scan.filesScanned + ' / ' + data.scan.filesFound + ' · ' + data.scan.records,
    data.scan.partial ? text('report_partial') : text('report_sample'),
    '',
    '## ' + text('report_problem'),
    notes.trim() || text('report_problem_placeholder'),
    '',
    '## ' + text('report_error'),
    group.level + ' · ' + group.count + ' ' + text('occurrences'),
    text('first') + ' ' + group.first,
    text('last') + ' ' + group.last,
    '',
    '## ' + text('packages'),
    text('attribution_help'),
    ...group.packages.map((item) => '- ' + item.name + ' ' + item.version + ' (' + text('type_' + item.type) + ') · ' + attributionText(item, text)),
    ...(!group.packages.length ? [text('no_packages')] : []),
    '',
    '## ' + text('sample'),
  ];
  const runs = group.sample.match(/`+/g) || [];
  const fence = '`'.repeat(Math.max(3, ...runs.map((run) => run.length + 1)));
  lines.push(fence + 'text', group.sample, fence);
  if (includeExtensions) {
    lines.push('', '## ' + text('extensions'));
    for (const item of data.environment.extensions) {
      lines.push('- ' + item.name + ' ' + item.version + ' (' + text(item.enabled ? 'enabled' : 'disabled') + ')');
    }
  }
  lines.push('', text('report_redaction'));
  return lines.join('\n');
}
