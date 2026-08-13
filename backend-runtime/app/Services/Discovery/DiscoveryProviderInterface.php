<?php

declare(strict_types=1);

namespace App\Services\Discovery;

interface DiscoveryProviderInterface
{
    public function key(): string;

    /** @return list<array{domain:string,url:string,title:?string,snippet:?string,provider:string,metadata:array}> */
    public function search(string $query, array $options = []): array;
}
