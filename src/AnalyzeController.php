<?php

namespace ElChoco\ErrorAnalyzer;

use Flarum\Foundation\Paths;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AnalyzeController implements RequestHandlerInterface
{
    public function __construct(private Paths $paths, private SettingsRepositoryInterface $settings)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertPermission($actor->isAdmin());
        $actor->assertAdmin();
        $headers = ['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache', 'X-Content-Type-Options' => 'nosniff'];
        $query = $request->getQueryParams();
        $maxFiles = filter_var($query['files'] ?? 5, FILTER_VALIDATE_INT);
        $minimum = $query['minimum'] ?? 'WARNING';
        if (!in_array($maxFiles, [5, 10, 20], true) || !in_array($minimum, ['DEBUG', 'WARNING', 'ERROR'], true)) {
            return new JsonResponse(['message' => 'Invalid scan options.'], 422, $headers);
        }
        $enabled = json_decode($this->settings->get('extensions_enabled', '[]'), true);
        try {
            $result = (new Analyzer())->scan($this->paths->base, $this->paths->storage, $this->paths->vendor,
                is_array($enabled) ? $enabled : [], $maxFiles, $minimum);

            return new JsonResponse($result, 200, $headers, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $exception) {
            return new JsonResponse(['message' => 'The log scan could not be completed.'], 503, $headers);
        }
    }
}
