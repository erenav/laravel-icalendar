<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Importing;

final class ImportResult
{
    /** @var list<int|string> */
    public array $created = [];

    /** @var list<int|string> */
    public array $updated = [];

    /** @var list<int|string> */
    public array $unchanged = [];

    /** @var list<int|string> Record identifiers and discarded revision identities. */
    public array $skipped = [];

    /** @var list<string> */
    public array $invalid = [];

    /** @return array<string, int> */
    public function counts(): array
    {
        return [
            'created' => count($this->created),
            'updated' => count($this->updated),
            'unchanged' => count($this->unchanged),
            'skipped' => count($this->skipped),
            'invalid' => count($this->invalid),
        ];
    }
}
