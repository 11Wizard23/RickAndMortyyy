/**
 * Pure UI rules, free of any DOM reference so tests/model.test.mjs can run them
 * under node: which section a character belongs to and how it moves when
 * starred, unstarred or deleted, plus the avatar retry policy.
 *
 * render.js draws, app.js decides when; this file owns the rules.
 */

export const empty = () => ({ starred: [], rows: [], total: 0, deleted: 0 });

/**
 * The section a character belongs to under the active scope, or null when the
 * scope has stopped matching it and it should leave the list altogether.
 */
export function sectionFor(character, scope) {
  if (scope === 'starred') return character.starred ? 'starred' : null;
  if (scope === 'others') return character.starred ? null : 'rows';

  return character.starred ? 'starred' : 'rows';
}

/** Remove a character from whichever section currently holds it. */
export function detach(model, id) {
  model.starred = model.starred.filter((c) => c.id !== id);
  model.rows = model.rows.filter((c) => c.id !== id);
}

/**
 * Insert a character into the section it belongs to, keeping the section in id
 * order — that is the order the API returns, so a re-seated character lands
 * back where it was rather than at the bottom.
 */
export function place(model, character, scope) {
  const section = sectionFor(character, scope);
  if (section === null) return;

  model[section].push(character);
  model[section].sort((a, b) => a.id - b.id);
}

/** Move a character after its starred flag changed. */
export function reseat(model, character, scope) {
  detach(model, character.id);
  place(model, character, scope);
}

/**
 * What to do after an avatar request failed on attempt #`attempt` (0-based).
 * Mirrors the server-side policy in ExponentialBackoff: 1s, 2s, 4s, capped.
 *
 * @returns {{action: 'retry', attempt: number, delay: number} | {action: 'fallback'}}
 */
export function imageRetry(attempt, maxRetries = 4, capSeconds = 8) {
  if (attempt >= maxRetries) return { action: 'fallback' };

  return {
    action: 'retry',
    attempt: attempt + 1,
    delay: Math.min(2 ** attempt, capSeconds) * 1000,
  };
}
