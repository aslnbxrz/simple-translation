<?php

namespace Aslnbxrz\SimpleTranslation\Enums;

enum CacheDriver: string
{
    case InMemory = 'in_memory';
    case Redis = 'redis';
}
