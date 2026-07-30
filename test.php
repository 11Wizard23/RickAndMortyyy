<?php
declare(strict_types=1);

/**
 * Self-checks. Run with: php test.php
 *
 * Not PHPUnit, and deliberately not assert(): production PHP ships with
 * zend.assertions=-1, which compiles assert() out and would make this file a
 * silent no-op.
 */

require __DIR__ . '/src/autoload.php';
require __DIR__ . '/tests/doubles.php';

use RickAndMorty\Api\RickAndMortyApi;
use RickAndMorty\Domain\Character;
use RickAndMorty\Domain\CharacterQuery;
use RickAndMorty\Domain\Scope;
use RickAndMorty\Exception\BadRequestException;
use RickAndMorty\Exception\TransportException;
use RickAndMorty\Exception\UnknownActionException;
use RickAndMorty\Exception\UpstreamException;
use RickAndMorty\Filter\AttributeFilter;
use RickAndMorty\Filter\FilterChain;
use RickAndMorty\Filter\NotDeletedFilter;
use RickAndMorty\Filter\ScopeFilter;
use RickAndMorty\Http\CachingHttpClient;
use RickAndMorty\Http\ExponentialBackoff;
use RickAndMorty\Http\HttpResponse;
use RickAndMorty\Http\RetryingHttpClient;
use RickAndMorty\Service\CharacterListService;
use RickAndMorty\Web\Request;

$checks = 0;
$failed = 0;

function ok(bool $condition, string $what): void
{
    global $checks, $failed;
    $checks++;
    if (!$condition) {
        $failed++;
        fwrite(STDERR, "FAIL: $what\n");
    }
}

function throws(string $class, callable $fn, string $what): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        ok($e instanceof $class, $what . ' (got ' . $e::class . ')');

        return;
    }
    ok(false, $what . ' (nothing was thrown)');
}

function character(
    int $id,
    string $name = 'Rick',
    string $species = 'Human',
    string $status = 'Alive',
    string $gender = 'Male',
    string $type = ''
): Character {
    return new Character($id, $name, "img/$id.jpeg", $species, $status, $gender, 'Earth', $type);
}

/** @param array<int,array<string,mixed>> $rows */
$idsOf = static fn (array $rows): array => array_column($rows, 'id');

// ── ExponentialBackoff ──────────────────────────────────────────────────────
$backoff = new ExponentialBackoff(8);

ok($backoff->delayFor(0, null) === 1, 'first retry waits 1s');
ok($backoff->delayFor(1, null) === 2, 'second retry doubles');
ok($backoff->delayFor(2, null) === 4, 'third retry doubles again');
ok($backoff->delayFor(9, null) === 8, 'exponential growth is capped');
ok($backoff->delayFor(0, 3) === 3, 'Retry-After wins over the default');
ok($backoff->delayFor(5, 2) === 2, 'Retry-After wins over a larger default');
ok($backoff->delayFor(0, 9999) === 8, 'a hostile Retry-After is capped');
ok($backoff->delayFor(1, 0) === 2, 'a zero Retry-After falls back to backoff');
ok($backoff->delayFor(1, -5) === 2, 'a negative Retry-After falls back to backoff');

// ── HttpResponse classification ─────────────────────────────────────────────
ok((new HttpResponse(429, ''))->isTransient(), 'rate limits are transient');
ok((new HttpResponse(503, ''))->isTransient(), 'unavailable is transient');
ok(!(new HttpResponse(404, ''))->isTransient(), 'not-found is a real answer, not a fault');
ok(!(new HttpResponse(400, ''))->isTransient(), 'bad request is not transient');
ok((new HttpResponse(404, ''))->isNotFound(), '404 is recognised');
ok((new HttpResponse(200, ''))->isOk(), '200 is ok');

// ── RetryingHttpClient ──────────────────────────────────────────────────────
$sleeper = new RecordingSleeper();
$inner   = new FakeHttpClient([
    new HttpResponse(429, '', 3),
    new HttpResponse(500, ''),
    new HttpResponse(200, '{"ok":true}'),
]);

ok(
    (new RetryingHttpClient($inner, $backoff, $sleeper, 3))->get('/x')->status === 200,
    'retries a 429 and a 500, then succeeds'
);
ok($inner->calls === 3, 'took exactly 3 attempts');
ok($sleeper->slept === [3, 2], 'honoured Retry-After first, then backed off');

$sleeper = new RecordingSleeper();
$inner   = new FakeHttpClient(array_fill(0, 9, new HttpResponse(429, '')));
ok(
    (new RetryingHttpClient($inner, $backoff, $sleeper, 3))->get('/x')->status === 429,
    'gives up and returns the last response'
);
ok($inner->calls === 4, 'one initial attempt plus three retries');
ok($sleeper->slept === [1, 2, 4], 'backed off between every attempt');

$inner = new FakeHttpClient([new HttpResponse(404, '')]);
(new RetryingHttpClient($inner, $backoff, new RecordingSleeper(), 3))->get('/x');
ok($inner->calls === 1, 'a 404 is never retried');

$inner = new FakeHttpClient([new TransportException('connection reset'), new HttpResponse(200, '{}')]);
ok(
    (new RetryingHttpClient($inner, $backoff, new RecordingSleeper(), 3))->get('/x')->status === 200,
    'transport failures are retried too'
);

$inner = new FakeHttpClient(array_fill(0, 9, new TransportException('down')));
throws(
    TransportException::class,
    fn () => (new RetryingHttpClient($inner, $backoff, new RecordingSleeper(), 2))->get('/x'),
    'a permanently dead upstream still throws'
);

// ── CachingHttpClient ───────────────────────────────────────────────────────
$inner  = new FakeHttpClient([new HttpResponse(200, 'first'), new HttpResponse(200, 'second')]);
$client = new CachingHttpClient($inner, new ArrayCache());

ok($client->get('/a')->body === 'first', 'first call reaches the inner client');
ok($client->get('/a')->body === 'first', 'second call is served from cache');
ok($inner->calls === 1, 'the cache prevented a second request');
ok($client->get('/b')->body === 'second', 'a different url is a different key');

$client = new CachingHttpClient(
    new FakeHttpClient([new HttpResponse(500, 'boom'), new HttpResponse(200, 'fine')]),
    new ArrayCache()
);
$client->get('/c');
ok($client->get('/c')->body === 'fine', 'errors are never cached');

// ── Character ───────────────────────────────────────────────────────────────
$rick = Character::fromApi([
    'id' => 1, 'name' => 'Rick Sanchez', 'image' => 'x.jpeg', 'species' => 'Human',
    'status' => 'Alive', 'gender' => 'Male', 'location' => ['name' => 'Citadel of Ricks'],
]);

ok($rick->location === 'Citadel of Ricks', 'location is flattened out of the nested object');
ok(Character::fromApi([])->location === 'unknown', 'a missing location degrades to unknown');
ok($rick->matches('species', 'human'), 'attribute matching is case-insensitive');
ok(Character::fromApi(['species' => 'Humanoid'])->matches('species', 'Human'), 'matching is loose, like the API');
ok(!$rick->matches('species', 'Alien'), 'a non-match is rejected');
ok($rick->matches('name', ''), 'an empty needle matches everything');
ok($rick->toArray(true)['starred'] === true, 'starred is supplied at render time');

// The API has no "occupation" — the mockup's third field is invented. `type` is
// the nearest real field: a species subtype, and blank for most characters.
ok(!array_key_exists('occupation', $rick->toArray(false)), 'there is no occupation field to expose');
ok($rick->type === '', 'a character without a type gets an empty string, not null');
ok(Character::fromApi(['type' => 'Genetic experiment'])->type === 'Genetic experiment', 'type is carried through');
ok(Character::fromApi(['type' => '  Parasite  '])->type === 'Parasite', 'a padded type is trimmed');
ok(Character::fromApi([])->type === '', 'a missing type degrades to blank, not "unknown"');
ok(character(1, 'Alan', 'Human', 'Dead', 'Male', 'Superhuman')->toArray(false)['type'] === 'Superhuman', 'type reaches the payload');

// ── Filters ─────────────────────────────────────────────────────────────────
$everyone = [character(1, 'Rick'), character(2, 'Morty'), character(3, 'Beth', 'Human', 'Alive', 'Female')];
$ids      = static fn (array $cs): array => array_map(static fn (Character $c): int => $c->id, $cs);

ok($ids((new FilterChain(new NotDeletedFilter([2])))->apply($everyone)) === [1, 3], 'soft-deleted are removed');
ok($ids((new FilterChain(new ScopeFilter(Scope::Starred, [1, 3])))->apply($everyone)) === [1, 3], 'scope=starred keeps only starred');
ok($ids((new FilterChain(new ScopeFilter(Scope::Others, [1, 3])))->apply($everyone)) === [2], 'scope=others keeps only the rest');
ok($ids((new FilterChain(new ScopeFilter(Scope::All, [1])))->apply($everyone)) === [1, 2, 3], 'scope=all keeps everyone');
ok($ids((new FilterChain(new AttributeFilter(new CharacterQuery(gender: 'Female'))))->apply($everyone)) === [3], 'attributes narrow by gender');
ok($ids((new FilterChain(new NotDeletedFilter([3]), new ScopeFilter(Scope::Others, [1])))->apply($everyone)) === [2], 'chained filters all apply');
ok(array_keys((new FilterChain(new NotDeletedFilter([1])))->apply($everyone)) === [0, 1], 'results re-index for JSON');
ok((new FilterChain())->apply($everyone) === $everyone, 'an empty chain passes everything');

// ── RickAndMortyApi ─────────────────────────────────────────────────────────
$body = json_encode([
    'info'    => ['pages' => 42, 'count' => 826],
    'results' => [['id' => 1, 'name' => 'Rick', 'location' => ['name' => 'Earth']]],
]);
$spy    = new FakeHttpClient([new HttpResponse(200, $body)]);
$result = (new RickAndMortyApi($spy, 'http://stub'))->search(new CharacterQuery(species: 'Human'), 2);

ok($result->total === 826 && $result->pages === 42, 'paging info is carried through');
ok($result->characters[0] instanceof Character, 'raw results become Characters');
ok($result->hasPageAfter(41) && !$result->hasPageAfter(42), 'hasPageAfter respects the page count');
ok(str_contains($spy->urls[0], 'page=2') && str_contains($spy->urls[0], 'species=Human'), 'filters reach the query string');
ok(!str_contains($spy->urls[0], 'status='), 'empty filters are left out of the query string');

$api = new RickAndMortyApi(new FakeHttpClient([new HttpResponse(404, '')]), 'http://stub');
ok($api->search(new CharacterQuery(name: 'zzz'), 1)->total === 0, 'a 404 search means no results, not an error');

$api = new RickAndMortyApi(new FakeHttpClient([new HttpResponse(404, '')]), 'http://stub');
ok($api->findByIds([9999]) === [], 'a 404 by id yields an empty list');

$spy = new FakeHttpClient([new HttpResponse(200, '{"id":1,"name":"Rick"}')]);
ok(count((new RickAndMortyApi($spy, 'http://stub'))->findByIds([1])) === 1, 'a single id returns a bare object');

$spy = new FakeHttpClient([]);
ok((new RickAndMortyApi($spy, 'http://stub'))->findByIds([]) === [] && $spy->calls === 0, 'no ids means no request');

throws(
    UpstreamException::class,
    fn () => (new RickAndMortyApi(new FakeHttpClient([new HttpResponse(200, 'not json')]), 'http://stub'))
        ->search(new CharacterQuery(), 1),
    'malformed JSON is rejected'
);
throws(
    UpstreamException::class,
    fn () => (new RickAndMortyApi(new FakeHttpClient([new HttpResponse(403, '')]), 'http://stub'))
        ->search(new CharacterQuery(), 1),
    'an unusable status is rejected'
);

// ── CharacterListService ────────────────────────────────────────────────────
$repo    = new FakeRepository([character(1, 'Rick'), character(2, 'Morty'), character(3, 'Beth')], 3, 9);
$state   = new ArrayUserState([2], []);
$payload = (new CharacterListService($repo, $state))->list(new CharacterQuery(), 1);

ok($idsOf($payload['starred']) === [2], 'the starred block holds the starred character');
ok($idsOf($payload['characters']) === [1, 3], 'the starred character is not repeated below');
ok($payload['hasMore'] === true, 'more pages are advertised');
ok($payload['total'] === 9, 'total comes from the upstream count');
ok($payload['deleted'] === 0, 'nothing is deleted yet');

$payload = (new CharacterListService($repo, $state))->list(new CharacterQuery(), 2);
ok($payload['starred'] === [], 'the starred block only appears on page 1');
ok($idsOf($payload['characters']) === [1, 2, 3], 'later pages are not de-duplicated against it');

$payload = (new CharacterListService($repo, new ArrayUserState([2], [3])))->list(new CharacterQuery(), 1);
ok($idsOf($payload['characters']) === [1], 'soft-deleted characters never reach the payload');
ok($payload['deleted'] === 1, 'the deleted count is reported');

$payload = (new CharacterListService($repo, new ArrayUserState([2], [2])))->list(new CharacterQuery(), 1);
ok($payload['starred'] === [], 'a deleted character is dropped from the starred block too');

$payload = (new CharacterListService($repo, $state))->list(new CharacterQuery(scope: Scope::Starred), 1);
ok($idsOf($payload['starred']) === [2], 'scope=starred still shows the starred block');
ok($payload['characters'] === [], 'scope=starred issues no upstream search');
ok($payload['hasMore'] === false, 'scope=starred is never paginated');
ok($payload['total'] === 1, 'scope=starred counts the starred block');

$payload = (new CharacterListService($repo, $state))->list(new CharacterQuery(scope: Scope::Others), 1);
ok($payload['starred'] === [], 'scope=others hides the starred block');
ok($idsOf($payload['characters']) === [1, 3], 'scope=others excludes starred characters');

// Starred characters are fetched by id, so nothing upstream filtered them.
$repo    = new FakeRepository([character(1, 'Rick', 'Human'), character(3, 'Birdperson', 'Bird')], 1, 2);
$payload = (new CharacterListService($repo, new ArrayUserState([3], [])))->list(new CharacterQuery(species: 'Human'), 1);
ok($payload['starred'] === [], 'a starred character failing the filter is dropped from the block');

// ── Request ─────────────────────────────────────────────────────────────────
ok((new Request([]))->action() === 'list', 'action defaults to list');
ok((new Request(['page' => '7']))->page() === 7, 'page is parsed');
ok((new Request(['page' => '-3']))->page() === 1, 'a negative page clamps to 1');
ok((new Request(['page' => 'abc']))->page() === 1, 'a non-numeric page clamps to 1');
ok((new Request(['id' => '12']))->id() === 12, 'a valid id is accepted');
ok((new Request(['name' => '  Rick ']))->string('name') === 'Rick', 'input is trimmed');
ok((new Request(['scope' => 'nonsense']))->toCharacterQuery()->scope === Scope::All, 'an unknown scope falls back to all');
ok((new Request(['scope' => 'others']))->toCharacterQuery()->scope === Scope::Others, 'a known scope is honoured');
ok((new Request(['name' => ['array']]))->string('name') === '', 'array input cannot smuggle past the boundary');

foreach (['abc', '0', '-1', ''] as $bad) {
    throws(BadRequestException::class, fn () => (new Request(['id' => $bad]))->id(), "id \"$bad\" is rejected");
}

// ── Container dispatch ──────────────────────────────────────────────────────
throws(
    UnknownActionException::class,
    fn () => (new RickAndMorty\Container())->action('drop-database'),
    'an unknown action is refused'
);

echo $failed === 0
    ? "$checks checks passed\n"
    : "$failed of $checks checks FAILED\n";

exit($failed === 0 ? 0 : 1);
