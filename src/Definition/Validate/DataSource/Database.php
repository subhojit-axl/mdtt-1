<?php

declare(strict_types=1);

namespace Mdtt\Definition\Validate\DataSource;

class Database implements Type
{
    /**
     * @inheritDoc
     */
    public function validate(array $rawDataSourceDefinition): bool
    {
        return isset($rawDataSourceDefinition['database']);
    }
}
