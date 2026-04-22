<?php

declare(strict_types=1);

namespace App\Domain\I18n;

use PDO;

final class I18nLoader
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * @return array<string,string> key_name → value
     */
    public function forLocale(string $locale): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT key_name, value FROM i18n_strings WHERE locale = :locale'
        );
        $stmt->execute([':locale' => $locale]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['key_name']] = (string) $row['value'];
        }
        return $out;
    }
}
