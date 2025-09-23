<?php

namespace Aslnbxrz\SimpleTranslation\Stores\Contracts;

interface StoreDriver
{
    /** Return map [key => value] or [] if file missing */
    public function read(string $scope, string $locale): array;

    /** Overwrite full map (atomic if possible) */
    public function write(string $scope, string $locale, array $map): bool;

    /** Append/merge a single key => value (create file if absent) */
    public function upsert(string $scope, string $locale, string $key, string $value): bool;

    /** Where files live (debug/ops purpose) */
    public function path(string $scope, string $locale): string;
}