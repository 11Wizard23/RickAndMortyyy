/**
 * Avatar loading.
 *
 * Avatars come from the same host as the API, so they hit the same 429s — and
 * an <img> has no retry of its own: it gives up and renders broken. This module
 * gives them the loading state and the backoff that the element lacks.
 *
 * Entirely self-contained: it works off `data-url` in the markup, so nothing
 * has to re-register anything when the list repaints.
 */

import { imageRetry } from './model.js';

const MAX_RETRIES = 4;

// heroicons/solid/user, as a data URI — shown once the retries are spent.
const FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent(
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#B9C0C9">'
  + '<path d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0'
  + ' .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z"/></svg>'
);

/** A fresh URL each attempt, so the browser actually re-requests. */
const attemptUrl = (url, n) => `${url}${url.includes('?') ? '&' : '?'}retry=${n}`;

/** Spread the delay, so a whole page of avatars doesn't retry in lockstep. */
const jitter = (delay) => delay * (0.7 + Math.random() * 0.6);

const isAvatar = (el) => el.tagName === 'IMG' && Boolean(el.dataset.url);

function onLoaded(event) {
  const img = event.target;
  if (!isAvatar(img)) return;

  // Stops the disc pulsing, and reveals the image if a retry brought it back
  // (the CSS hides anything carrying data-attempt, to suppress the browser's
  // broken-image glyph). Clearing the counter also means a later, unrelated
  // failure starts from a full retry budget.
  img.removeAttribute('data-loading');
  delete img.dataset.attempt;
}

function onFailed(event) {
  const img = event.target;
  if (!isAvatar(img) || img.hasAttribute('data-failed')) return;

  const plan = imageRetry(Number(img.dataset.attempt ?? 0), MAX_RETRIES);

  if (plan.action === 'fallback') {
    img.setAttribute('data-failed', '');
    img.removeAttribute('data-loading');
    // The placeholder is meant to be seen, and data-attempt is what the CSS
    // hides. Clear it here rather than relying on the data URI firing `load`.
    delete img.dataset.attempt;
    img.src = FALLBACK;

    return;
  }

  img.dataset.attempt = String(plan.attempt);
  img.setAttribute('data-loading', '');
  setTimeout(() => {
    // A repaint may have replaced this element in the meantime; leave it be.
    if (img.isConnected) img.src = attemptUrl(img.dataset.url, plan.attempt);
  }, jitter(plan.delay));
}

/**
 * Reveal avatars that are already decoded, synchronously.
 *
 * The CSS keeps every avatar hidden until `data-loading` is dropped, which is
 * what guarantees a failed request never flashes the broken-image glyph. But
 * `load` fires in a later task, so on a repaint an image sitting in the browser
 * cache would still blink out for a frame. Checking `complete` right after the
 * markup is inserted — before the browser paints — closes that gap.
 *
 * Call it after writing avatar markup into the document.
 */
export function settleAvatars(root = document) {
  root.querySelectorAll('img[data-loading]').forEach((img) => {
    if (img.complete && img.naturalWidth > 0) {
      img.removeAttribute('data-loading');
      delete img.dataset.attempt;
    }
  });
}

/**
 * load and error don't bubble, so listen during the capture phase. One pair of
 * listeners covers every avatar in both panes, however often they're repainted.
 */
export function installAvatarRetry() {
  document.addEventListener('load', onLoaded, true);
  document.addEventListener('error', onFailed, true);
}
