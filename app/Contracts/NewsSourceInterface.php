<?php

namespace App\Contracts;

interface NewsSourceInterface
{
    public function fetch(): array;

    public function normalize(array $data): array;
}
