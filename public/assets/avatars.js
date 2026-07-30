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
  if (isAvatar(event.target)) event.target.removeAttribute('data-loading');
}

function onFailed(event) {
  const img = event.target;
  if (!isAvatar(img) || img.hasAttribute('data-failed')) return;

  const plan = imageRetry(Number(img.dataset.attempt ?? 0), MAX_RETRIES);

  if (plan.action === 'fallback') {
    img.setAttribute('data-failed', '');
    img.removeAttribute('data-loading');
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
 * load and error don't bubble, so listen during the capture phase. One pair of
 * listeners covers every avatar in both panes, however often they're repainted.
 */
export function installAvatarRetry() {
  document.addEventListener('load', onLoaded, true);
  document.addEventListener('error', onFailed, true);
}
