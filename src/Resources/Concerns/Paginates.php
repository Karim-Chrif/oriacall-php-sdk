<?php

namespace Oriacall\Resources\Concerns;

use Generator;

trait Paginates
{
    /** @param array<string, mixed> $options */
    public function paginate(array $options = []): Generator
    {
        $cursor = $options['cursor'] ?? null;

        do {
            $pageOptions = $options;
            if ($cursor !== null) {
                $pageOptions['cursor'] = $cursor;
            } else {
                unset($pageOptions['cursor']);
            }

            $response = $this->list($pageOptions);

            foreach (($response->data['data'] ?? []) as $item) {
                yield $item;
            }

            $cursor = $response->data['pagination']['nextCursor'] ?? null;
        } while ($cursor);
    }
}
