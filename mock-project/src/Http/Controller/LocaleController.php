<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LocaleController
{
    private const SUPPORTED = ['sv', 'en'];

    /**
     * @param array<string,string> $args
     */
    public function set(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $code = (string) ($args['code'] ?? '');
        if (!in_array($code, self::SUPPORTED, true)) {
            return $response->withStatus(404);
        }

        $_SESSION['locale'] = $code;

        $referer = $request->getHeaderLine('Referer');
        $redirect = $referer !== '' ? $referer : '/tickets';

        return $response->withStatus(302)->withHeader('Location', $redirect);
    }
}
