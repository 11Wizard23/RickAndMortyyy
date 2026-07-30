/**
 * Self-checks for the sidebar list model. Run with: node tests/model.test.mjs
 *
 * These are the rules that decide where a character ends up after it is
 * starred, unstarred or deleted — the part that used to be papered over by
 * refetching the whole list from the server.
 */
'use strict';

import * as M from '../public/assets/model.js';

let checks = 0;
let failed = 0;

function ok(condition, what) {
  checks++;
  if (!condition) {
    failed++;
    console.error(`FAIL: ${what}`);
  }
}

const character = (id, starred = false) => ({ id, name: `#${id}`, starred });
const ids = (section) => section.map((c) => c.id);

// ── sectionFor ──────────────────────────────────────────────────────────────
ok(M.sectionFor(character(1, true), 'all') === 'starred', 'starred goes to the starred block');
ok(M.sectionFor(character(1, false), 'all') === 'rows', 'unstarred goes to the main list');
ok(M.sectionFor(character(1, true), 'starred') === 'starred', 'scope=starred keeps a starred character');
ok(M.sectionFor(character(1, false), 'starred') === null, 'scope=starred drops an unstarred character');
ok(M.sectionFor(character(1, false), 'others') === 'rows', 'scope=others keeps an unstarred character');
ok(M.sectionFor(character(1, true), 'others') === null, 'scope=others drops a starred character');

// ── detach ──────────────────────────────────────────────────────────────────
let model = { starred: [character(2, true)], rows: [character(1), character(3)] };
M.detach(model, 3);
ok(ids(model.rows).join() === '1', 'detach removes from the main list');
M.detach(model, 2);
ok(model.starred.length === 0, 'detach removes from the starred block');
M.detach(model, 999);
ok(model.rows.length === 1, 'detaching an absent id is harmless');

// ── place keeps id order ────────────────────────────────────────────────────
model = { starred: [], rows: [character(1), character(3), character(4)] };
M.place(model, character(2), 'all');
ok(ids(model.rows).join() === '1,2,3,4', 'a character lands back in id order, not at the bottom');

model = { starred: [], rows: [] };
M.place(model, character(5, true), 'others');
ok(model.starred.length === 0 && model.rows.length === 0, 'a character the scope rejects is placed nowhere');

// ── reseat: starring ────────────────────────────────────────────────────────
model = { starred: [character(4, true)], rows: [character(1), character(2), character(3)] };
const two = model.rows[1];
two.starred = true;
M.reseat(model, two, 'all');
ok(ids(model.starred).join() === '2,4', 'starring moves it into the starred block, in order');
ok(ids(model.rows).join() === '1,3', 'and out of the main list');

// ── reseat: unstarring ──────────────────────────────────────────────────────
model = { starred: [character(2, true), character(4, true)], rows: [character(1), character(3)] };
const four = model.starred[1];
four.starred = false;
M.reseat(model, four, 'all');
ok(ids(model.starred).join() === '2', 'unstarring leaves the starred block');
ok(ids(model.rows).join() === '1,3,4', 'and rejoins the main list in id order');

// ── reseat under a narrowed scope ───────────────────────────────────────────
model = { starred: [character(2, true)], rows: [] };
const flipped = model.starred[0];
flipped.starred = false;
M.reseat(model, flipped, 'starred');
ok(model.starred.length === 0 && model.rows.length === 0, 'unstarring under scope=starred removes it from view');

model = { starred: [], rows: [character(7)] };
const seven = model.rows[0];
seven.starred = true;
M.reseat(model, seven, 'others');
ok(model.starred.length === 0 && model.rows.length === 0, 'starring under scope=others removes it from view');

// A character must never end up in both sections at once.
model = { starred: [], rows: [character(1)] };
const one = model.rows[0];
one.starred = true;
M.reseat(model, one, 'all');
M.reseat(model, one, 'all');
ok(model.starred.length === 1 && model.rows.length === 0, 'reseating twice does not duplicate');

// ── imageRetry ──────────────────────────────────────────────────────────────
ok(M.imageRetry(0, 4).action === 'retry', 'a first failure is retried');
ok(M.imageRetry(0, 4).delay === 1000, 'the first retry waits 1s');
ok(M.imageRetry(1, 4).delay === 2000, 'the second doubles');
ok(M.imageRetry(2, 4).delay === 4000, 'the third doubles again');
ok(M.imageRetry(3, 4).delay === 8000, 'the fourth doubles again');
ok(M.imageRetry(0, 4).attempt === 1, 'the attempt counter advances');
ok(M.imageRetry(3, 4).attempt === 4, 'the last retry is numbered');
ok(M.imageRetry(4, 4).action === 'fallback', 'the budget runs out after maxRetries');
ok(M.imageRetry(9, 4).action === 'fallback', 'and stays spent past it');
ok(M.imageRetry(0, 0).action === 'fallback', 'a zero budget never retries');
ok(M.imageRetry(6, 99).delay === 8000, 'the delay is capped, not doubled forever');
ok(M.imageRetry(2, 99, 3).delay === 3000, 'the cap is configurable');

// The client policy must not outlast the server's own retry window by much:
// four avatar attempts (1+2+4+8s) against the API client's 1+2+4s.
const budget = [0, 1, 2, 3].reduce((sum, n) => sum + M.imageRetry(n, 4).delay, 0);
ok(budget === 15000, 'the whole avatar retry budget is 15s');

// ── empty ───────────────────────────────────────────────────────────────────
const fresh = M.empty();
ok(fresh.starred.length === 0 && fresh.rows.length === 0, 'a fresh model has no rows');
ok(fresh.total === 0 && fresh.deleted === 0, 'a fresh model has zeroed counters');
ok(M.empty() !== M.empty(), 'each model is a distinct object');

console.log(failed === 0 ? `${checks} checks passed` : `${failed} of ${checks} checks FAILED`);
process.exit(failed === 0 ? 0 : 1);
