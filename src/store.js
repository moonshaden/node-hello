'use strict';

const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

/**
 * Content store.
 *
 * The whole site is one JSON document. That is a deliberate choice over a
 * database: the foundation publishes a few dozen records, the file diffs
 * cleanly in git, a backup is `cp`, and there is no server to administer or
 * migration to run. Writes are atomic (write-temp-then-rename) so a crash
 * mid-save can never leave a truncated file behind.
 */

const DEFAULT_FILE = path.join(__dirname, '..', 'data', 'content.json');

const COLLECTIONS = ['announcements', 'scholarships', 'recipients', 'pages'];

const EMPTY = {
  site: {
    name: 'LEO Foundation',
    tagline: '',
    timezone: 'America/Phoenix',
  },
  enrollment: { type: 'annual', opensOn: '11-01', closesOn: '03-31' },
  announcements: [],
  scholarships: [],
  recipients: [],
  pages: [],
};

class Store {
  constructor(file = process.env.DATA_FILE || DEFAULT_FILE) {
    this.file = file;
    this.data = this.#read();
  }

  #read() {
    if (!fs.existsSync(this.file)) {
      return structuredClone(EMPTY);
    }
    const parsed = JSON.parse(fs.readFileSync(this.file, 'utf8'));
    const data = { ...structuredClone(EMPTY), ...parsed };
    data.site = { ...EMPTY.site, ...(parsed.site || {}) };
    for (const name of COLLECTIONS) {
      if (!Array.isArray(data[name])) data[name] = [];
    }
    return data;
  }

  /** Re-read from disk. Used by tests and by the dev-mode reload middleware. */
  reload() {
    this.data = this.#read();
    return this.data;
  }

  save() {
    const dir = path.dirname(this.file);
    fs.mkdirSync(dir, { recursive: true });
    const tmp = path.join(dir, `.${path.basename(this.file)}.${process.pid}.tmp`);
    fs.writeFileSync(tmp, `${JSON.stringify(this.data, null, 2)}\n`);
    fs.renameSync(tmp, this.file);
    return this.data;
  }

  get site() {
    return this.data.site;
  }

  get enrollment() {
    return this.data.enrollment;
  }

  list(collection) {
    if (!COLLECTIONS.includes(collection)) {
      throw new Error(`unknown collection: ${collection}`);
    }
    return this.data[collection];
  }

  find(collection, id) {
    return this.list(collection).find((item) => item.id === id) || null;
  }

  findBySlug(collection, slug) {
    return this.list(collection).find((item) => item.slug === slug) || null;
  }

  /** Insert when `record.id` is absent or unknown, otherwise merge in place. */
  upsert(collection, record) {
    const items = this.list(collection);
    const existing = record.id ? items.find((item) => item.id === record.id) : null;
    if (existing) {
      Object.assign(existing, record);
      this.save();
      return existing;
    }
    const created = { ...record, id: record.id || crypto.randomUUID() };
    items.push(created);
    this.save();
    return created;
  }

  remove(collection, id) {
    const items = this.list(collection);
    const index = items.findIndex((item) => item.id === id);
    if (index === -1) return false;
    items.splice(index, 1);
    this.save();
    return true;
  }

  /** Move an item up or down within its collection's display order. */
  reorder(collection, id, direction) {
    const items = this.list(collection);
    const index = items.findIndex((item) => item.id === id);
    if (index === -1) return false;
    const target = direction === 'up' ? index - 1 : index + 1;
    if (target < 0 || target >= items.length) return false;
    [items[index], items[target]] = [items[target], items[index]];
    this.save();
    return true;
  }

  updateSite(patch) {
    Object.assign(this.data.site, patch);
    this.save();
    return this.data.site;
  }

  updateEnrollment(patch) {
    this.data.enrollment = { ...this.data.enrollment, ...patch };
    this.save();
    return this.data.enrollment;
  }
}

/** Turn a title into a URL-safe slug. */
function slugify(value) {
  return String(value || '')
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 80);
}

module.exports = { Store, slugify, COLLECTIONS, DEFAULT_FILE };
