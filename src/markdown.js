'use strict';

const { marked } = require('marked');

marked.setOptions({ gfm: true, breaks: true });

// Admin-authored copy is trusted, but stripping script/iframe/event handlers
// keeps a pasted-in snippet from becoming a stored XSS by accident.
const DANGEROUS = /<\s*\/?\s*(script|iframe|object|embed|form)\b[^>]*>/gi;
const EVENT_ATTR = /\son[a-z]+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi;
const JS_URL = /((?:href|src)\s*=\s*["']?)\s*javascript:/gi;
// A lone '#' is clamped up to h2 rather than becoming a second page h1, which
// is how the PHP build's Markdown reads it too.
const BODY_H1 = /<(\/?)h1>/gi;

function render(value) {
  if (!value) return '';
  return marked
    .parse(String(value))
    .replace(DANGEROUS, '')
    .replace(EVENT_ATTR, '')
    .replace(JS_URL, '$1#')
    .replace(BODY_H1, '<$1h2>');
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

// A long page — the FAQ runs to thirteen questions — reads as a wall in one
// prose column, so its headings get stable ids and the view puts a jump-to
// index above them. The slug rules are mirrored in Markdown::withAnchors() in
// the PHP build, and a test asserts the two agree on the seeded content.
const HEADING = /<h2>([\s\S]*?)<\/h2>/g;
const ENTITY = { '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&#039;': "'", '&#39;': "'" };

function slugify(inner) {
  return inner
    .replace(/<[^>]+>/g, '')
    .replace(/&(?:amp|lt|gt|quot|#0?39);/g, (m) => ENTITY[m])
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

/** Rendered markdown plus its anchored top-level headings, in document order. */
function renderSections(value) {
  const headings = [];
  const seen = new Map();

  const html = render(value).replace(HEADING, (whole, inner) => {
    const base = slugify(inner) || 'section';
    const nth = (seen.get(base) || 0) + 1;
    seen.set(base, nth);
    const id = nth > 1 ? `${base}-${nth}` : base;
    headings.push({ id, html: inner });
    return `<h2 id="${id}">${inner}</h2>`;
  });

  return { html, headings };
}

module.exports = { render, renderInline, renderSections };

