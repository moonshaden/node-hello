'use strict';

const { marked } = require('marked');

marked.setOptions({ gfm: true, breaks: true });

// Admin-authored copy is trusted, but stripping script/iframe/event handlers
// keeps a pasted-in snippet from becoming a stored XSS by accident.
const DANGEROUS = /<\s*\/?\s*(script|iframe|object|embed|form)\b[^>]*>/gi;
const EVENT_ATTR = /\son[a-z]+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi;
const JS_URL = /((?:href|src)\s*=\s*["']?)\s*javascript:/gi;

function render(value) {
  if (!value) return '';
  return marked
    .parse(String(value))
    .replace(DANGEROUS, '')
    .replace(EVENT_ATTR, '')
    .replace(JS_URL, '$1#');
}

/** Markdown with the block wrapper stripped -- for one-line summaries. */
function renderInline(value) {
  if (!value) return '';
  return marked
    .parseInline(String(value))
    .replace(DANGEROUS, '')
    .replace(EVENT_ATTR, '')
    .replace(JS_URL, '$1#');
}

module.exports = { render, renderInline };
