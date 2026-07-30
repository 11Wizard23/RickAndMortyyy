<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rick and Morty list</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<style type="text/tailwindcss">
  @theme {
    --font-sans: "Inter", ui-sans-serif, system-ui, sans-serif;
    --color-canvas: #E4E4E4;
    --color-ink: #1D1E20;
    --color-muted: #808C9A;
    --color-line: #EDEDED;
    --color-brand: #8054C7;
    --color-brand-soft: #EEE3FF;
    --color-brand-ink: #5A3696;
    --color-fav: #53C629;
    --color-fav-soft: #63D83833;   /* 20% alpha, so it sits on any backdrop */
    --color-fav-ink: #3B8520;
    --color-results: #2563eb;
  }

  @layer components {
    /* A character row in the sidebar list. */
    .row {
      @apply relative flex items-center gap-3 rounded-lg px-3 py-3 cursor-pointer
             transition-colors border-b border-line;
    }
    .row:hover { @apply bg-black/[.02]; }
    .row-on {
      @apply bg-brand-soft border-transparent;
    }
    .row-on + .row { @apply border-t-0; }

    .section-label {
      @apply px-3 pt-6 pb-2 text-[11px] font-semibold uppercase tracking-[.08em] text-muted;
    }

    /* Filter option pill. */
    .pill {
      @apply h-9 rounded-lg border border-line bg-white text-[13px] font-medium text-ink
             transition-colors cursor-pointer hover:border-brand/40;
    }
    .pill-on {
      @apply border-transparent bg-brand-soft text-brand;
    }

    /* Label / value pair in the detail pane. */
    .field { @apply border-b border-line py-4; }
    .field dt { @apply text-[13px] font-semibold text-ink; }
    .field dd { @apply text-[13px] text-muted mt-0.5; }

    .icon-btn { @apply grid place-items-center rounded-md transition-colors cursor-pointer; }
  }

  /* Avatars.
     The disc lives on the wrapper, not on the <img>. A failed or in-flight
     image paints the browser's own broken-image glyph over its background, and
     there is no way to suppress that glyph while keeping the background — so
     the <img> stays fully transparent until it has actually decoded, and then
     fades in over the disc. */
  @keyframes rm-spin  { to { transform: rotate(1turn); } }
  @keyframes rm-pulse { 50% { opacity: .35; } }

  /* Grid, so the image and the spinner share one cell and the spinner centres
     itself without any margin arithmetic. */
  .avatar {
    display: grid;
    place-items: center;
    flex: none;
    overflow: hidden;
    border-radius: 9999px;
    background-color: var(--color-line);
  }
  .avatar > img {
    grid-area: 1 / 1;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: opacity .25s ease;
  }

  /* Hidden until proven loaded: the browser paints its broken-image glyph the
     instant a request fails, a frame before any error handler can react, so
     reacting is too late — the image must never be visible before it has
     decoded. avatars.js clears data-loading synchronously for anything already
     in the cache, which is what stops this blinking the list on every repaint. */
  .avatar > img[data-loading] { opacity: 0; }

  /* Spinner while the image is in flight or waiting on a retry. Sized in %, so
     the same rule fits the 32px row avatar and the 72px one in the detail. */
  .avatar:has(> img[data-loading])::after {
    content: "";
    grid-area: 1 / 1;
    width: 45%;
    height: 45%;
    border-radius: 9999px;
    border: 2px solid rgba(0, 0, 0, .08);
    border-top-color: var(--color-brand);
    animation: rm-spin .7s linear infinite;
  }

  @media (prefers-reduced-motion: reduce) {
    /* Trade the rotation for a fade — still legible as "busy", no spinning. */
    .avatar:has(> img[data-loading])::after {
      animation: rm-pulse 1.4s ease-in-out infinite;
    }
    .avatar > img { transition: none; }
  }

  /* A soft-deleted row slides out instead of vanishing mid-click. */
  .row-leaving {
    opacity: 0;
    transform: translateX(-10px);
    transition: opacity .18s ease, transform .18s ease;
    pointer-events: none;
  }
  @media (prefers-reduced-motion: reduce) {
    .row-leaving { transition: none; }
  }

  /* The filter toggle carries the selected-pill colours while the sheet is open,
     and while filters are actually applied. The open state rides on the aria
     attribute the button already maintains; the applied state comes from
     render.js, off the same count that drives the "N Filters" badge.
     Unlayered, so it beats the button's own hover utility. */
  #toggle-filters[aria-expanded="true"],
  #toggle-filters.filters-on {
    background-color: var(--color-brand-soft);
  }

  /* On the selected row the starred heart sits in a white disc. Unlayered, so it
     beats the button's own hover utility without needing !important. */
  .row-on [data-star-on] {
    background-color: #fff;
    border-radius: 9999px;
  }

  /* Explicit show/hide, so JS never has to fight utility ordering. */
  [data-toggle] { display: none; }
  [data-toggle="flex"].open { display: flex; }
  [data-toggle="block"].open { display: block; }

  /* Mobile is one pane at a time; desktop is always master + detail. */
  @media (max-width: 767px) {
    body[data-view="detail"] #pane-list { display: none; }
    body[data-view="list"] #pane-detail { display: none; }
  }
  @media (min-width: 768px) {
    #hdr-search { display: none !important; }
    #hdr-default { display: block !important; }
  }
</style>
</head>

<body class="h-full bg-canvas font-sans text-ink antialiased" data-view="list">
<div class="mx-auto flex h-full max-w-[1440px] bg-white shadow-sm">

  <!-- ── Sidebar: search + character list ─────────────────────────────── -->
  <aside id="pane-list" class="flex w-full shrink-0 flex-col border-r border-line md:w-[375px]">

    <div class="px-6 pt-6 pb-2">
      <!-- Default header -->
      <h1 id="hdr-default" data-toggle="block" class="open text-[22px] font-bold tracking-tight">Rick and Morty list</h1>

      <!-- Mobile header once a search/filter is active -->
      <div id="hdr-search" data-toggle="flex" class="items-center justify-between">
        <button data-act="clear-filters" class="icon-btn size-8 -ml-2 hover:bg-black/5" aria-label="Back">
          <!-- heroicons/outline/arrow-left -->
          <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        </button>
        <span class="text-[15px] font-semibold">Advanced search</span>
        <button data-act="clear-filters" class="text-[15px] font-medium text-brand cursor-pointer">Done</button>
      </div>

      <!-- Search + filter toggle -->
      <div class="relative mt-4">
        <!-- heroicons/outline/magnifying-glass -->
        <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
        <input id="search" type="search" autocomplete="off" placeholder="Search or filter results"
               class="h-11 w-full rounded-lg bg-[#F7F7F8] pl-10 pr-11 text-[13px] placeholder:text-muted
                      outline-none ring-1 ring-transparent transition focus:bg-white focus:ring-brand/40">
        <button id="toggle-filters" aria-label="Filters" aria-expanded="false"
                class="icon-btn absolute right-1.5 top-1/2 size-8 -translate-y-1/2 rounded-lg
                       text-brand transition hover:bg-brand-soft">
          <!-- heroicons/outline/adjustments-vertical -->
          <svg class="size-[18px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 13.5V3.75m0 9.75a1.5 1.5 0 0 1 0 3m0-3a1.5 1.5 0 0 0 0 3m0 3.75V16.5m12-3V3.75m0 9.75a1.5 1.5 0 0 1 0 3m0-3a1.5 1.5 0 0 0 0 3m0 3.75V16.5m-6-9V3.75m0 3.75a1.5 1.5 0 0 1 0 3m0-3a1.5 1.5 0 0 0 0 3m0 9.75V10.5"/></svg>
        </button>

        <!-- Filters: dropdown on desktop, full screen on mobile -->
        <form id="filters" data-toggle="flex" class="fixed inset-0 z-20 flex-col bg-white p-6
                     md:absolute md:inset-auto md:left-0 md:right-0 md:top-[52px] md:z-10 md:rounded-xl
                     md:border md:border-line md:p-4 md:shadow-lg">
          <div class="mb-6 flex items-center md:hidden">
            <button type="button" data-act="close-filters" class="icon-btn size-8 -ml-2 hover:bg-black/5" aria-label="Back">
              <!-- heroicons/outline/arrow-left -->
              <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            </button>
            <span class="flex-1 text-center text-[15px] font-semibold">Filters</span>
            <span class="size-8"></span>
          </div>

          <div class="flex-1 space-y-4 overflow-y-auto">
            <fieldset data-group="scope">
              <legend class="mb-2 text-[13px] text-muted">Character</legend>
              <div class="grid grid-cols-3 gap-2">
                <button type="button" class="pill" value="all">All</button>
                <button type="button" class="pill" value="starred">Starred</button>
                <button type="button" class="pill" value="others">Others</button>
              </div>
            </fieldset>

            <fieldset data-group="species">
              <legend class="mb-2 text-[13px] text-muted">Specie</legend>
              <div class="grid grid-cols-3 gap-2">
                <button type="button" class="pill" value="">All</button>
                <button type="button" class="pill" value="Human">Human</button>
                <button type="button" class="pill" value="Alien">Alien</button>
              </div>
            </fieldset>

            <fieldset data-group="status">
              <legend class="mb-2 text-[13px] text-muted">Status</legend>
              <div class="grid grid-cols-3 gap-2">
                <button type="button" class="pill" value="">All</button>
                <button type="button" class="pill" value="Alive">Alive</button>
                <button type="button" class="pill" value="Dead">Dead</button>
                <button type="button" class="pill col-span-3" value="unknown">Unknown</button>
              </div>
            </fieldset>

            <fieldset data-group="gender">
              <legend class="mb-2 text-[13px] text-muted">Gender</legend>
              <div class="grid grid-cols-3 gap-2">
                <button type="button" class="pill" value="">All</button>
                <button type="button" class="pill" value="Female">Female</button>
                <button type="button" class="pill" value="Male">Male</button>
                <button type="button" class="pill col-span-3" value="Genderless">Genderless</button>
              </div>
            </fieldset>
          </div>

          <button id="apply" type="submit" disabled
                  class="mt-6 h-11 w-full rounded-lg bg-[#F1F1F1] text-[14px] font-semibold text-muted
                         transition enabled:cursor-pointer enabled:bg-brand-ink enabled:text-white
                         enabled:hover:brightness-90 md:mt-4 md:h-9">Filter</button>
        </form>
      </div>

      <!-- Result summary, shown while any filter or query is active -->
      <div id="results-bar" data-toggle="flex" class="mt-4 items-center justify-between">
        <span id="results-count" class="text-[13px] font-semibold text-results"></span>
        <span id="results-filters" data-toggle="block" class="rounded-full bg-fav-soft px-2.5 py-1 text-[11px] font-semibold text-fav-ink"></span>
      </div>
    </div>

    <div id="list" aria-live="polite" aria-busy="true" class="min-h-0 flex-1 overflow-y-auto px-3 pb-4"></div>

    <div id="deleted-bar" data-toggle="flex" class="items-center justify-between border-t border-line px-6 py-3 text-[12px] text-muted">
      <span id="deleted-count"></span>
      <button data-act="restore" class="font-semibold text-brand cursor-pointer">Restore all</button>
    </div>
  </aside>

  <!-- ── Detail ───────────────────────────────────────────────────────── -->
  <section id="pane-detail" class="flex flex-1 flex-col overflow-y-auto px-6 py-6 md:px-10 md:py-8">
    <button data-act="back" class="icon-btn mb-4 size-8 -ml-2 hover:bg-black/5 md:hidden" aria-label="Back to list">
      <!-- heroicons/outline/arrow-left -->
      <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
    </button>
    <div id="detail-body" class="max-w-[760px]"></div>
  </section>

</div>
<script type="module" src="assets/app.js"></script>
</body>
</html>
