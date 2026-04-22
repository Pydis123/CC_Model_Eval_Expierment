<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class LocaleMiddleware implements MiddlewareInterface
{
    private const SUPPORTED = ['sv', 'en'];
    private const DEFAULT_LOCALE = 'sv';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $locale = $_SESSION['locale'] ?? self::DEFAULT_LOCALE;
        if (!in_array($locale, self::SUPPORTED, true)) {
            $locale = self::DEFAULT_LOCALE;
        }

        $_SESSION['locale'] = $locale;
        $request = $request->withAttribute('locale', $locale);

        return $handler->handle($request);
    }
}
