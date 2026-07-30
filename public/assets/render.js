/**
 * The view: every piece of HTML this app produces, plus the two functions that
 * write it into the page. Holds no state of its own — everything it draws is
 * passed in, which is what keeps app.js the only place state can change.
 */

const $ = (sel) => document.querySelector(sel);

const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;',
}[c]));

// ── icons ────────────────────────────────────────────────────────────────────
// Heroicons v2 (MIT, Tailwind Labs), inlined so the page stays self-contained.
// The icons used in static markup live in index.php; these are the ones only
// ever produced at runtime, so the two sets don't overlap.
const PATHS = {
  // heroicons/solid/heart
  heartSolid: 'm11.645 20.91-.007-.003-.022-.012a15.247 15.247 0 0 1-.383-.218 25.18 25.18 0 0 1-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0 1 12 5.052 5.5 5.5 0 0 1 16.312 3c2.974 0 5.438 2.322 5.438 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 0 1-4.244 3.17 15.247 15.247 0 0 1-.383.219l-.022.012-.007.004-.003.001a.752.752 0 0 1-.704 0l-.003-.001Z',
  // heroicons/outline/heart
  heartOutline: 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z',
  // heroicons/outline/trash
  trash: 'm14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.2v.916m7.5 0a48.667 48.667 0 0 0-7.5 0',
  // heroicons/outline/arrow-path
  arrowPath: 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99',
};

const outline = (name, cls) =>
  `<svg class="${cls}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="${PATHS[name]}"/></svg>`;

const solid = (name, cls) =>
  `<svg class="${cls}" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="${PATHS[name]}"/></svg>`;

const heart = (on) => (on
  ? solid('heartSolid', 'size-5 text-fav')
  : outline('heartOutline', 'size-5 text-[#D3D3D3]'));

const trash = (cls = 'size-5') => outline('trash', cls);

const spinner = outline('arrowPath', 'size-4 shrink-0 animate-spin text-brand');

// ── templates ────────────────────────────────────────────────────────────────
const rowHTML = (c, selected) => `
  <div class="row group ${c.id === selected ? 'row-on' : ''}" data-id="${c.id}">
    <span class="avatar size-8">
      <img src="${esc(c.image)}" data-url="${esc(c.image)}" data-loading alt="" loading="lazy"
           width="32" height="32">
    </span>
    <div class="min-w-0 flex-1">
      <p class="truncate text-[14px] font-semibold leading-tight">${esc(c.name)}</p>
      <p class="truncate text-[13px] text-muted">${esc(c.species)}</p>
    </div>
    <button data-act="delete" aria-label="Delete ${esc(c.name)}"
            class="icon-btn size-8 text-muted hover:bg-black/5 hover:text-red-500 md:opacity-0 md:group-hover:opacity-100">${trash()}</button>
    <button data-act="star" ${c.starred ? 'data-star-on' : ''} aria-label="${c.starred ? 'Unstar' : 'Star'} ${esc(c.name)}"
            class="icon-btn size-8 hover:bg-black/5">${heart(c.starred)}</button>
  </div>`;

const ghostRows = (n) => Array.from({ length: n }, () => `
  <div class="flex animate-pulse items-center gap-3 py-3">
    <div class="size-8 rounded-full bg-line"></div>
    <div class="flex-1 space-y-2">
      <div class="h-3 w-1/2 rounded bg-line"></div>
      <div class="h-2.5 w-1/4 rounded bg-line"></div>
    </div>
  </div>`).join('');

/** First load: fill the whole pane so the layout doesn't jump. */
export const skeleton = () => `<div class="px-3 pt-6">${ghostRows(7)}</div>`;

/** Appending a page: a few ghost rows plus an explicit "we are fetching" line. */
export const loader = () => `
  <div id="more" class="px-3">
    ${ghostRows(3)}
    <div class="flex items-center justify-center gap-2 pb-4 pt-1 text-[12px] font-medium text-muted">
      ${spinner}<span>Loading more characters…</span>
    </div>
  </div>`;

const emptyState = () => `
  <div class="px-6 py-16 text-center">
    <p class="text-[15px] font-semibold">No characters found</p>
    <p class="mt-1 text-[13px] text-muted">Try a different name or loosen the filters.</p>
  </div>`;

/** Closes off the list once there is nothing left to scroll for. */
const endOfResults = (shown) => `
  <p class="border-t border-line px-3 pt-4 pb-6 text-center text-[12px] text-muted">
    End of results · ${shown} character${shown === 1 ? '' : 's'}
  </p>`;

export const errorHTML = (msg, p) => `
  <div class="px-6 ${p === 1 ? 'py-16' : 'py-8'} text-center" data-error>
    <p class="text-[15px] font-semibold">${p === 1 ? 'Something went wrong' : "Couldn't load more"}</p>
    <p class="mt-1 text-[13px] text-muted">${esc(msg)}</p>
    <button data-act="retry" data-page="${p}"
            class="mt-4 h-9 rounded-lg bg-brand px-4 text-[13px] font-semibold text-white cursor-pointer">Try again</button>
  </div>`;

/**
 * One label/value pair. Blank values are skipped rather than rendered empty —
 * the API's `type` is missing for roughly 60% of characters.
 */
const field = (label, value) => (String(value ?? '').trim() === '' ? '' : `
    <div class="field"><dt>${esc(label)}</dt><dd>${esc(value)}</dd></div>`);

export const detailHTML = (c) => `
  <div class="relative w-fit">
    <span class="avatar size-[72px]">
      <img src="${esc(c.image)}" data-url="${esc(c.image)}" data-loading alt="${esc(c.name)}"
           width="72" height="72">
    </span>
    <button data-act="star" data-id="${c.id}" aria-label="${c.starred ? 'Unstar' : 'Star'} ${esc(c.name)}"
            class="icon-btn absolute -bottom-1 -right-1 size-7 rounded-full bg-white shadow-sm ring-1 ring-line">
      ${heart(c.starred)}
    </button>
  </div>
  <div class="mt-5 flex items-start justify-between gap-4">
    <h2 class="text-[24px] font-bold tracking-tight">${esc(c.name)}</h2>
    <button data-act="delete" data-id="${c.id}"
            class="icon-btn h-9 gap-2 rounded-lg px-3 text-[13px] font-medium text-muted ring-1 ring-line hover:bg-black/5 hover:text-red-500 flex">
      ${trash('size-4')}<span>Delete</span>
    </button>
  </div>
  <dl class="mt-4">
    ${field('Specie', c.species)}
    ${field('Status', c.status)}
    ${field('Location', c.location)}
    ${field('Type', c.type)}
  </dl>`;

export const placeholder = `
  <div class="grid h-full place-items-center text-center">
    <div>
      <p class="text-[15px] font-semibold">Select a character</p>
      <p class="mt-1 text-[13px] text-muted">Pick someone from the list to see their details.</p>
    </div>
  </div>`;

// ── painting ─────────────────────────────────────────────────────────────────

/**
 * Redraw the whole list from the model. Cheap enough to run on every change,
 * which is what lets starring and deleting skip the network entirely.
 */
export function paintList(el, model, { hasMore, selected }) {
  const section = (title, rows) =>
    `<p class="section-label">${title} (${rows.length})</p>`
    + rows.map((c) => rowHTML(c, selected)).join('');

  const shown = model.starred.length + model.rows.length;

  let html = '';
  if (model.starred.length) html += section('Starred characters', model.starred);
  if (model.rows.length) html += section('Characters', model.rows);

  // Rewriting innerHTML resets scrollTop; put the user back where they were.
  const top = el.scrollTop;
  el.innerHTML = html || emptyState();

  if (hasMore) el.insertAdjacentHTML('beforeend', '<div id="sentinel" class="h-4"></div>');
  else if (shown) el.insertAdjacentHTML('beforeend', endOfResults(shown));

  el.scrollTop = top;
}

/** The result bar, the deleted bar, and which header the mobile view shows. */
export function paintChrome(model, { filterCount, searching }) {
  $('#results-bar').classList.toggle('open', searching);
  $('#results-count').textContent = `${model.total} Result${model.total === 1 ? '' : 's'}`;
  $('#results-filters').textContent = filterCount ? `${filterCount} Filter${filterCount === 1 ? '' : 's'}` : '';
  $('#results-filters').classList.toggle('open', filterCount > 0);

  $('#hdr-default').classList.toggle('open', !searching);
  $('#hdr-search').classList.toggle('open', searching);

  $('#deleted-bar').classList.toggle('open', model.deleted > 0);
  $('#deleted-count').textContent = `${model.deleted} character${model.deleted === 1 ? '' : 's'} deleted`;
}

/** Highlight the selected row without redrawing the list. */
export function markSelected(el, id) {
  el.querySelectorAll('.row').forEach((row) => {
    row.classList.toggle('row-on', Number(row.dataset.id) === id);
  });
}
