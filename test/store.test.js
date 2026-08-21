'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const { Store, slugify } = require('../src/store');

function tempFile() {
  return path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'leo-store-')), 'content.json');
}

test('a missing file starts from an empty site rather than throwing', () => {
  const store = new Store(tempFile());
  assert.deepEqual(store.list('scholarships'), []);
  assert.equal(store.site.timezone, 'America/Phoenix');
});

test('writes survive a reload', () => {
  const file = tempFile();
  const store = new Store(file);
  const saved = store.upsert('scholarships', { name: 'Nursing' });
  assert.ok(saved.id);

  const reopened = new Store(file);
  assert.equal(reopened.list('scholarships')[0].name, 'Nursing');
});

test('upsert merges into an existing record instead of duplicating it', () => {
  const store = new Store(tempFile());
  const created = store.upsert('recipients', { name: 'Ada', year: '2025' });
  store.upsert('recipients', { id: created.id, name: 'Ada Lovelace' });

  assert.equal(store.list('recipients').length, 1);
  assert.equal(store.list('recipients')[0].name, 'Ada Lovelace');
  assert.equal(store.list('recipients')[0].year, '2025');
});

test('reorder moves a record and refuses to fall off either end', () => {
  const store = new Store(tempFile());
  const first = store.upsert('pages', { title: 'One' });
  const second = store.upsert('pages', { title: 'Two' });

  assert.equal(store.reorder('pages', second.id, 'up'), true);
  assert.deepEqual(store.list('pages').map((p) => p.title), ['Two', 'One']);
  assert.equal(store.reorder('pages', second.id, 'up'), false);
  assert.equal(store.reorder('pages', first.id, 'down'), false);
});

test('remove deletes only the named record', () => {
  const store = new Store(tempFile());
  const keep = store.upsert('pages', { title: 'Keep' });
  const drop = store.upsert('pages', { title: 'Drop' });

  assert.equal(store.remove('pages', drop.id), true);
  assert.equal(store.remove('pages', 'nope'), false);
  assert.deepEqual(store.list('pages').map((p) => p.id), [keep.id]);
});

test('an unknown collection is a programming error, not a silent empty list', () => {
  const store = new Store(tempFile());
  assert.throws(() => store.list('sponsors'), /unknown collection/);
});

test('slugs are url-safe and stable', () => {
  assert.equal(slugify('Joyce K. Smith Nursing Memorial Scholarship'), 'joyce-k-smith-nursing-memorial-scholarship');
  assert.equal(slugify('  Women & Chemistry  '), 'women-chemistry');
  assert.equal(slugify('Café Awards'), 'cafe-awards');
  assert.equal(slugify(''), '');
});
