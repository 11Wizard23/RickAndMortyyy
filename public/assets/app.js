/**
 * The controller: owns the state, talks to api.php, and decides when the view
 * redraws. All persistence lives on the server; all HTML lives in render.js.
 */

import * as Model from './model.js';
import * as View from './render.js';
import { installAvatarRetry } from './avatars.js';

const $ = (sel) => document.querySelector(sel);

const list = $('#list');
const detail = $('#detail-body');
const filters = $('#filters');
const applyButton = $('#apply');

const FILTER_KEYS = ['scope', 'species', 'status', 'gender'];
const DEFAULTS = { name: '', scope: 'all', species: '', status: '', gender: '' };

let applied = { ...DEFAULTS };   // what the list currently reflects
let draft = { ...DEFAULTS };     // what the filter sheet is showing

let page = 1;
let hasMore = false;
let loading = false;
let selected = null;
const cache = new Map();         // id -> character, so the detail pane needs no extra request

// What the sidebar is showing. Starring and deleting mutate this and repaint,
// so neither one costs a refetch, a skeleton, or the scroll position.
let model = Model.empty();

// ── drawing ──────────────────────────────────────────────────────────────────
const activeFilters = () => FILTER_KEYS.filter((k) => applied[k] !== DEFAULTS[k]).length;

function paint() {
  const filterCount = activeFilters();
  View.paintList(list, model, { hasMore, selected });
  View.paintChrome(model, { filterCount, searching: filterCount > 0 || applied.name !== '' });
}

function showDetail(id) {
  selected = id;
  const character = id && cache.get(id);

  detail.innerHTML = character ? View.detailHTML(character) : View.placeholder;
  detail.classList.toggle('h-full', !character);
  document.body.dataset.view = character ? 'detail' : 'list';
  View.markSelected(list, id);
}

// ── loading ──────────────────────────────────────────────────────────────────
const query = (p) => new URLSearchParams({ action: 'list', page: p, ...applied }).toString();

async function load(p = 1) {
  if (loading) return;
  loading = true;
  page = p;

  // Announce the fetch: skeleton on first load, ghost rows + spinner when appending.
  list.setAttribute('aria-busy', 'true');
  $('#sentinel')?.remove();
  if (p === 1) list.innerHTML = View.skeleton();
  else list.insertAdjacentHTML('beforeend', View.loader());

  try {
    const response = await fetch(`api.php?${query(p)}`);
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || `HTTP ${response.status}`);
    absorb(data, p);
  } catch (e) {
    // Keep the failure visible and retryable rather than silently stopping the scroll.
    $('#more')?.remove();
    if (p === 1) list.innerHTML = View.errorHTML(e.message, 1);
    else list.insertAdjacentHTML('beforeend', View.errorHTML(e.message, p));
  } finally {
    loading = false;
    list.setAttribute('aria-busy', 'false');
  }
}

/** Fold a server page into the model, then draw. */
function absorb(data, p) {
  $('#more')?.remove();
  hasMore = data.hasMore;

  if (p === 1) {
    model.starred = data.starred;
    model.rows = data.characters;
  } else {
    model.rows.push(...data.characters);
  }
  model.total = data.total;
  model.deleted = data.deleted;

  // Same object references as the model, so flipping `starred` updates both.
  [...data.starred, ...data.characters].forEach((c) => cache.set(c.id, c));

  paint();
  if (selected && !cache.has(selected)) showDetail(null);
}

// ── mutations ────────────────────────────────────────────────────────────────
const post = (action, id) =>
  fetch(`api.php?action=${action}&id=${id}`, { method: 'POST' })
    .then((res) => { if (!res.ok) throw new Error(`HTTP ${res.status}`); });

const mutate = (action, id) => (action === 'star' ? toggleStar(id) : softDelete(id));

/** Optimistic: flip locally and repaint now, reconcile with the server after. */
async function toggleStar(id) {
  const character = cache.get(id);
  if (!character) return;

  const reseat = () => {
    Model.reseat(model, character, applied.scope);
    paint();
    if (selected === id) detail.innerHTML = View.detailHTML(character);
  };

  character.starred = !character.starred;
  reseat();

  try {
    await post('star', id);
  } catch {
    character.starred = !character.starred;   // put it back the way it was
    reseat();
  }
}

/** Fade the row out, then drop it from the model. */
async function softDelete(id) {
  const row = list.querySelector(`.row[data-id="${id}"]`);
  if (row) {
    row.classList.add('row-leaving');
    await new Promise((resolve) => setTimeout(resolve, 180));
  }

  Model.detach(model, id);
  cache.delete(id);
  model.deleted++;
  if (selected === id) showDetail(null);
  paint();

  try {
    await post('delete', id);
  } catch {
    // The server never saw it; refetch rather than guess at the old position.
    load(1);
  }
}

// ── filter sheet ─────────────────────────────────────────────────────────────
function paintPills() {
  filters.querySelectorAll('fieldset').forEach((fieldset) => {
    const key = fieldset.dataset.group;
    fieldset.querySelectorAll('.pill').forEach((pill) => {
      pill.classList.toggle('pill-on', pill.value === draft[key]);
    });
  });
  applyButton.disabled = FILTER_KEYS.every((k) => draft[k] === applied[k]);
}

function openFilters(open) {
  filters.classList.toggle('open', open);
  $('#toggle-filters').setAttribute('aria-expanded', String(open));
  if (open) {
    draft = { ...applied };
    paintPills();
  }
}

// ── events ───────────────────────────────────────────────────────────────────
$('#toggle-filters').addEventListener('click', () => openFilters(!filters.classList.contains('open')));

filters.addEventListener('click', (e) => {
  const pill = e.target.closest('.pill');
  if (pill) {
    draft[pill.closest('fieldset').dataset.group] = pill.value;
    return paintPills();
  }
  if (e.target.closest('[data-act="close-filters"]')) openFilters(false);
});

filters.addEventListener('submit', (e) => {
  e.preventDefault();
  applied = { ...draft, name: applied.name };
  openFilters(false);
  load(1);
});

let debounce;
$('#search').addEventListener('input', (e) => {
  clearTimeout(debounce);
  debounce = setTimeout(() => {
    applied.name = e.target.value.trim();
    load(1);
  }, 300);
});

list.addEventListener('click', (e) => {
  const retry = e.target.closest('[data-act="retry"]');
  if (retry) {
    retry.closest('[data-error]').remove();
    return load(Number(retry.dataset.page));
  }

  const row = e.target.closest('.row');
  if (!row) return;

  const id = Number(row.dataset.id);
  const action = e.target.closest('[data-act]')?.dataset.act;

  if (action === 'star' || action === 'delete') mutate(action, id);
  else showDetail(id);
});

detail.addEventListener('click', (e) => {
  const button = e.target.closest('[data-act]');
  if (!button) return;
  if (button.dataset.act === 'back') return showDetail(null);
  mutate(button.dataset.act, Number(button.dataset.id));
});

document.body.addEventListener('click', async (e) => {
  if (e.target.closest('[data-act="back"]')) showDetail(null);

  if (e.target.closest('[data-act="clear-filters"]')) {
    applied = { ...DEFAULTS };
    $('#search').value = '';
    load(1);
  }

  if (e.target.closest('[data-act="restore"]')) {
    await fetch('api.php?action=restore', { method: 'POST' });
    load(1);
  }
});

// Infinite scroll: pull the next page when the sentinel scrolls into view. The
// sentinel is replaced on every repaint, so re-observe it after each mutation.
const observer = new IntersectionObserver((entries) => {
  if (entries[0].isIntersecting && hasMore && !loading) load(page + 1);
}, { root: list, rootMargin: '250px' });

new MutationObserver(() => {
  const sentinel = $('#sentinel');
  if (sentinel) observer.observe(sentinel);
}).observe(list, { childList: true });

// ── start ────────────────────────────────────────────────────────────────────
installAvatarRetry();
showDetail(null);
load(1);
