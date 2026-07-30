<?php
declare(strict_types=1);

namespace RickAndMorty;

use RickAndMorty\Api\CharacterRepository;
use RickAndMorty\Api\RickAndMortyApi;
use RickAndMorty\Cache\FileCache;
use RickAndMorty\Exception\UnknownActionException;
use RickAndMorty\Http\CachingHttpClient;
use RickAndMorty\Http\CurlHttpClient;
use RickAndMorty\Http\ExponentialBackoff;
use RickAndMorty\Http\HttpClient;
use RickAndMorty\Http\RetryingHttpClient;
use RickAndMorty\Http\SystemSleeper;
use RickAndMorty\Service\CharacterListService;
use RickAndMorty\State\SessionUserState;
use RickAndMorty\State\UserState;
use RickAndMorty\Web\Action;
use RickAndMorty\Web\DeleteAction;
use RickAndMorty\Web\ListAction;
use RickAndMorty\Web\RestoreAction;
use RickAndMorty\Web\StarAction;

/**
 * The single place that knows which concrete classes exist. Everything else
 * depends on interfaces, so this is the only file to touch when swapping an
 * implementation.
 */
final class Container
{
    private ?UserState $state = null;

    public function __construct(
        private readonly string $baseUrl = 'https://rickandmortyapi.com/api',
        private readonly ?string $cacheDir = null,
    ) {
    }

    /** @throws UnknownActionException */
    public function action(string $name): Action
    {
        $actions = [
            'list'    => fn (): Action => new ListAction($this->characterList()),
            'star'    => fn (): Action => new StarAction($this->state()),
            'delete'  => fn (): Action => new DeleteAction($this->state()),
            'restore' => fn (): Action => new RestoreAction($this->state()),
        ];

        if (!isset($actions[$name])) {
            throw UnknownActionException::named($name);
        }

        return $actions[$name]();
    }

    public function characterList(): CharacterListService
    {
        return new CharacterListService($this->repository(), $this->state());
    }

    public function repository(): CharacterRepository
    {
        return new RickAndMortyApi($this->httpClient(), $this->baseUrl);
    }

    /**
     * Cache outermost so a cached hit costs nothing, retry inside it so only
     * real network calls are ever retried.
     */
    public function httpClient(): HttpClient
    {
        return new CachingHttpClient(
            new RetryingHttpClient(new CurlHttpClient(), new ExponentialBackoff(), new SystemSleeper()),
            new FileCache($this->cacheDir ?? sys_get_temp_dir()),
        );
    }

    /** Shared: the session must not be opened more than once per request. */
    public function state(): UserState
    {
        return $this->state ??= new SessionUserState();
    }
}
