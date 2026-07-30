# Rick and Morty list

A small PHP 8 + TailwindCSS frontend for the [Rick and Morty REST API](https://rickandmortyapi.com/documentation/#rest),
built to the provided Figma mockup (master/detail list, filter sheet, starred characters).

No framework, no build step, no database.

---

## Running it

Requires **PHP 8.1+** with the `curl` extension and outbound internet access.

```bash
php -S localhost:8000 -t public
```

Open <http://localhost:8000>. That's it — Tailwind is loaded from a CDN and compiled in the
browser, so there is nothing to install or build.

To serve it from Apache/nginx instead, point the document root at this directory. The only
requirement is that PHP sessions work (they hold the starred / deleted state).

### Locking down what is reachable

The document root is **`public/`**. It holds `index.php`, `api.php` and `assets/` — nothing else.
Application code, the test suite and the deploy snippets live one level above it, so they are
unreachable *by construction*, not because a rule remembered to say so. `test.php` in particular
would run the whole suite against the upstream API if it were ever fetched.

Point your server at `public/`, not at the project root:

- **Apache** — `deploy/apache.conf` (`DocumentRoot .../public`). `public/.htaccess` handles the
  rest: no directory listings, no dotted paths, a few security headers.
- **nginx** — **ignores `.htaccess` entirely**, so `deploy/nginx.conf` has to be installed by
  hand. Note the ordering comment in it: the `/\.` denial must precede the `\.php$` handler,
  because nginx stops at the first matching regex location.

There is also a `.htaccess` at the project root, plus deny-all files in `src/` and `tests/`.
Those are a safety net for the one case the layout can't prevent: shared hosting where the
document root is fixed at the upload directory. Then the app still works from `/public/` and
nothing else is served.

Both paths are verified against the local Apache 2.4:

```
docroot = public/            (correct)
  /  /api.php  /assets/*.js                       200
  everything else                                 outside the tree

docroot = project root       (misconfigured)
  /public/  /public/api.php  /public/assets/*.js  200
  /src/**  /tests/**  /test.php  /deploy/**       403
  /.htaccess  /.git/config  /  (listing)          403
```

### Deploying

Any PHP host works. Upload the whole directory, point the document root at `public/`, make sure
`curl` is enabled, done.
Free options that need no configuration: [InfinityFree](https://infinityfree.net),
[000webhost](https://000webhost.com), Railway/Render with the official `php` image,
or a `$5` VPS with `php-fpm` + nginx.

---

## Layout

```
public/                       THE DOCUMENT ROOT — the only directory served over HTTP
  index.php    HTML shell + Tailwind theme (design tokens, component classes)
  api.php      Front controller: wiring, dispatch, exception → status code
  .htaccess    Apache hygiene: no listings, no dotted paths, security headers

  assets/      ES modules — no bundler, no build step
    app.js       Controller: owns the state, calls api.php, decides when to redraw
    render.js    View: every piece of HTML, plus the functions that write it. Stateless
    model.js     Rules: section placement, avatar retry policy. No DOM, so node can test it
    avatars.js   Avatar loading state and retry. Self-contained

deploy/                       Server config. apache.conf and nginx.conf, both rooted at public/
test.php + tests/doubles.php  PHP self-checks (`php test.php`)
tests/model.test.mjs          JS self-checks (`node tests/model.test.mjs`)
.htaccess                     Safety net for a misconfigured document root

src/
  autoload.php                PSR-4 autoloader (no Composer, nothing to install)
  Container.php               The only file that names concrete classes

  Http/          HttpClient interface; CurlHttpClient + Retrying/Caching decorators;
                 ExponentialBackoff, and Sleeper behind an interface so retries test instantly
  Cache/         Cache interface + FileCache
  Api/           CharacterRepository interface + RickAndMortyApi, SearchResult
  Domain/        Character, CharacterQuery, Scope
  Filter/        CharacterFilter interface + NotDeleted / Scope / Attribute, composed by FilterChain
  State/         UserState interface + SessionUserState (starred, soft-deleted)
  Service/       CharacterListService — the grouping rules
  Web/           Request (the trust boundary), Action interface + one class per endpoint
  Exception/     BadRequest, Upstream, Transport, UnknownAction
```

PHP owns everything that touches the network or persists: it calls the upstream API, caches
responses, and stores the per-visitor starred/deleted sets in the session. The browser only
renders what `api.php` hands it — which is what makes the loading skeletons and infinite scroll
possible without a full page reload.

The frontend is split the same way as the backend: `model.js` holds rules, `render.js` holds
presentation, `app.js` holds state and orchestration. Loaded as native ES modules
(`<script type="module">`), so dependencies are explicit imports rather than globals, and the
rule module can be imported straight into a node test.

### How it hangs together

`Container` builds one chain and hands the front controller an `Action`:

```
CachingHttpClient → RetryingHttpClient → CurlHttpClient
        ↑ cache hits cost nothing      ↑ only real network calls are retried

RickAndMortyApi (CharacterRepository) ─┐
                                       ├→ CharacterListService → ListAction
SessionUserState (UserState) ──────────┘
```

Each SOLID letter earns its keep here rather than being decoration:

- **SRP** — `CurlHttpClient` only speaks HTTP, `RetryingHttpClient` only retries, `FileCache` only
  stores. Before the refactor one `rm_get()` function did all three.
- **OCP** — a new filter rule is a new `CharacterFilter` class added to a `FilterChain`; no
  existing filter changes. Same for a new endpoint: a new `Action`, one line in `Container`.
- **LSP** — the three `HttpClient` implementations are interchangeable; the decorators add
  behaviour without changing the contract, which is why they stack in any order.
- **ISP** — `Cache`, `Sleeper`, and `UserState` are small interfaces, so a test double is a few
  lines rather than a mocking framework. Interfaces that earned no second implementation (not
  even a test double) were removed rather than kept for symmetry.
- **DIP** — `CharacterListService` depends on `CharacterRepository` and `UserState`, never on
  cURL or `$_SESSION`. That is what lets the suite exercise it with no network and no session.

Upstream responses are cached in the system temp directory for 5 minutes, so browsing back and
forth doesn't re-hit the API.

### Rate limiting

The Rick and Morty API returns `429` under load. `RetryingHttpClient` retries those (and any
`5xx`, and outright transport failures) up to 3 times, waiting for the `Retry-After` header when
one is sent and backing off exponentially — 1s, 2s, 4s — when it isn't. Both are capped at 8
seconds so a bad header can't park a worker.

The retry happens *inside* the PHP request, so the browser's fetch stays pending and the loading
state remains on screen the whole time. Only after every attempt fails does the client show an
error with a **Try again** button, which resumes from the page that failed rather than resetting
the list.

Run `php test.php` to exercise it. `Sleeper` is an interface, so the retry tests assert on the
exact delays (`[3, 2]` for a `Retry-After: 3` followed by a plain `500`) without waiting.

**Avatars hit the same limit.** They are served from the same host, and an `<img>` has no retry
of its own — it renders broken and gives up. So `app.js` watches `error` events during the
capture phase and reapplies the same policy client-side: up to 4 attempts at 1s, 2s, 4s, 8s
(jittered, so a screenful of avatars doesn't retry in lockstep), each with a fresh `?retry=N` so
the browser really re-requests. The disc pulses throughout, and once the ~15s budget is spent a
neutral `heroicons/solid/user` glyph takes its place instead of a broken image.

---

## Internal API

All endpoints return JSON. Errors come back as `{"error": "..."}` with a `4xx`/`5xx` status.
State-changing calls use `POST`.

### `GET api.php?action=list`

Returns one page of characters, already filtered and grouped.

| Param     | Values                                     | Notes |
|-----------|--------------------------------------------|-------|
| `page`    | integer ≥ 1                                | Upstream page. Default `1`. |
| `name`    | free text                                  | Partial, case-insensitive match. |
| `status`  | `Alive` · `Dead` · `unknown`               | |
| `species` | `Human` · `Alien` · any species string     | Partial match, e.g. `Human` also matches `Humanoid`. |
| `gender`  | `Female` · `Male` · `Genderless` · `unknown` | |
| `scope`   | `all` · `starred` · `others`               | The mockup's *Character* filter. Default `all`. |

```jsonc
{
  "starred":    [ /* starred characters, first page only */ ],
  "characters": [ /* the rest */ ],
  "page":       1,
  "hasMore":    true,     // another upstream page exists
  "total":      826,      // drives the "N Results" label
  "deleted":    3         // soft-deleted count
}
```

Each character is trimmed to what the UI draws:

```json
{
  "id": 1, "name": "Rick Sanchez", "image": "https://…/1.jpeg",
  "species": "Human", "status": "Alive", "gender": "Male",
  "location": "Citadel of Ricks", "type": "", "starred": false, "deleted": false
}
```

Soft-deleted characters are filtered out server-side and never reach the client.

### `POST api.php?action=star&id=<int>`

Toggles the star. → `{"starred": true}`

### `POST api.php?action=delete&id=<int>`

Soft-deletes a character: it stays in the upstream API but is hidden from every response for this
visitor, and is un-starred if it was starred. → `{"deleted": 3}`

### `POST api.php?action=restore`

Restores every soft-deleted character. → `{"deleted": 0}`

---

## How the requirements map

| Requirement | Where |
|---|---|
| Name, image, species, status, location | Row shows name/image/species; the detail pane shows all five, plus *Type* when the API supplies one. See the deviations note on *Occupation*. |
| Responsive, Flexbox + Grid | Flexbox for the two-pane shell and rows; CSS Grid for the filter pills. Below `768px` one pane shows at a time, exactly like the mobile frames. |
| Pagination | `?page=N` against the upstream API. |
| Infinite scroll | An `IntersectionObserver` on a sentinel row pulls the next page. Images use `loading="lazy"`. Once the last page is in, the sentinel is replaced by an "End of results · N characters" footer. |
| Loading state | Skeleton rows on first load; ghost rows + a spinner when appending a page, held for the whole retry window. `aria-busy` mirrors it for screen readers. |
| No results | "No characters found" empty state (the API answers `404` for an empty search; that is translated to an empty result, not an error). |
| Search by status / species / gender | The filter sheet, alongside the mockup's *Character* and *Specie* groups. |
| Soft delete | Trash icon on row hover and in the detail pane; a "N characters deleted — Restore all" bar appears at the bottom of the list. Stored in the session. |
| Starring / deleting | Applied optimistically against a client-side model and repainted immediately — no refetch, no skeleton, no scroll jump. The `POST` reconciles in the background and reverts the row if it fails. |
| No PHP framework | Standard library plus `curl`; hand-rolled PSR-4 autoloader, no Composer. |
| OOP + SOLID | See *How it hangs together* above. `php test.php` covers it: 86 checks, no network, no session, no mocking library. The frontend is layered the same way — see `assets/`. |

---

## Known deviations from the mockup

- **Status and Gender filter groups** were added to the filter sheet (the mockup only draws
  *Character* and *Specie*). They reuse the same pill styling.
- **Soft delete has no design.** The trash affordance and the restore bar were added in the
  mockup's visual language.
- **Occupation does not exist.** The API's character schema is `id, name, status, species, type,
  gender, origin, location, image, episode, url, created` — there is no occupation. The mockup's
  own example gives *Abadango Cluster Princess* the occupation "Princess", but that character
  returns `type: ""` from the API, so even the design's sample data is invented.

  The detail pane therefore shows **Location** in that slot (the brief requires it), plus a
  **Type** row — the nearest real field, a species subtype like "Genetic experiment" or "Human
  with antennae". It is blank for roughly 60% of characters, so the row is only rendered when
  there is something to put in it. Labelling `type` as "Occupation" would have been a lie in the
  UI and empty most of the time.
- Icons are [Heroicons](https://heroicons.com) v2 (MIT, Tailwind Labs), inlined as SVG paths
  rather than pulled from a package — no build step, no extra request, and the page stays
  self-contained. Each one is commented with its source name (`heroicons/outline/trash`). The
  24px outline set is used at 20px and above; the mockup's filter glyph maps to
  `adjustments-vertical`. Heroicons has no spinner, so the "loading more" indicator is
  `arrow-path` under `animate-spin`.
- Tailwind runs from the CDN browser build. For production, compile it once with the Tailwind CLI
  and ship a static stylesheet instead.
