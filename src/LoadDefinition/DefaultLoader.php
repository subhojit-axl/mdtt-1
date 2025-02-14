<?php

declare(strict_types=1);

namespace Mdtt\LoadDefinition;

use Mdtt\Definition\DefaultDefinition;
use Mdtt\Destination\Database as DatabaseDestination;
use Mdtt\Exception\SetupException;
use Mdtt\Source\Database as DatabaseSource;
use Mdtt\Test\DefaultTest;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Yaml\Yaml;

class DefaultLoader implements Load
{
    private LoggerInterface $logger;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function scan(): array
    {
        $ymlTestDefinitions = glob("tests/mdtt/*.yml", GLOB_ERR);
        if ($ymlTestDefinitions === false) {
            throw new IOException("Error occurred while loading test definitions");
        }

        $yamlTestDefinitions = glob("tests/mdtt/*.yaml", GLOB_ERR);
        if ($yamlTestDefinitions === false) {
            throw new IOException("Error occurred while loading test definitions");
        }

        $testDefinitions = array_merge([], $ymlTestDefinitions, $yamlTestDefinitions);
        if (!$testDefinitions) {
            throw new SetupException("No test definitions found.");
        }

        return $testDefinitions;
    }

    /**
     * @inheritDoc
     */
    public function validate(): iterable
    {
        /** @var array<array<string>>|array<array<array<string>>> $testDefinitions */
        $testDefinitions = array_map(static function ($testDefinition) {
            return Yaml::parseFile($testDefinition);
        }, $this->scan());
        $parsedTestDefinitions = [];

        foreach ($testDefinitions as $testDefinition) {
            $this->doValidate($testDefinition);

            $parsedTestDefinition = new DefaultDefinition($this->logger);

            /** @var string $id */
            $id = $testDefinition['id'];
            $parsedTestDefinition->setId($id);

            /** @var array<string> $sourceInformation */
            $sourceInformation = $testDefinition['source'];
            /** @var string $sourceData */
            $sourceData = $sourceInformation['data'];
            /** @var string $sourceDatabase */
            $sourceDatabase = $sourceInformation['database'];
            $parsedTestDefinition->setSource((new DatabaseSource($sourceData, $sourceDatabase)));

            /** @var array<string> $destinationInformation */
            $destinationInformation = $testDefinition['destination'];
            /** @var string $destinationData */
            $destinationData = $destinationInformation['data'];
            /** @var string $destinationDatabase */
            $destinationDatabase = $destinationInformation['database'];
            $parsedTestDefinition->setDestination((new DatabaseDestination($destinationData, $destinationDatabase)));

            /** @var array<array<string>> $tests */
            $tests = $testDefinition['tests'];
            /** @var array<\Mdtt\Test\Test> $parsedTests */
            $parsedTests = [];
            foreach ($tests as $test) {
                /** @var string $sourceField */
                $sourceField = $test['sourceField'];
                /** @var string $destinationField */
                $destinationField = $test['destinationField'];

                $parsedTests[] = new DefaultTest($sourceField, $destinationField, $this->logger);
            }
            $parsedTestDefinition->setTests($parsedTests);

            /** @var ?string $description */
            $description = $testDefinition['description'] ?? null;
            if ($description) {
                $parsedTestDefinition->setDescription($description);
            }

            /** @var ?string $group */
            $group = $testDefinition['group'] ?? null;
            if ($group) {
                $parsedTestDefinition->setGroup($group);
            }

            $parsedTestDefinitions[] = $parsedTestDefinition;
        }

        return $parsedTestDefinitions;
    }

    /**
     * Validates the test definitions.
     * @param array<string>|array<array<string>> $parsedTestDefinition
     */
    private function doValidate(array $parsedTestDefinition): void
    {
        if (empty($parsedTestDefinition['id'])) {
            throw new SetupException("Test definition id is missing");
        }

        // TODO: Further validate source types to SQL, JSON, XML, CSV.
        if (is_array($parsedTestDefinition['source']) &&
          (empty($parsedTestDefinition['source']['type']) ||
          empty($parsedTestDefinition['source']['data']) ||
            empty($parsedTestDefinition['source']['database']))) {
            throw new SetupException("Test definition source is missing");
        }

        // TODO: Further validate destination types to SQL, JSON, XML.
        if (is_array($parsedTestDefinition['destination']) &&
          (empty($parsedTestDefinition['destination']['type']) ||
          empty($parsedTestDefinition['destination']['data']) ||
            empty($parsedTestDefinition['destination']['database']))) {
            throw new SetupException("Test definition destination is missing");
        }

        if (empty($parsedTestDefinition['tests']) && !is_array($parsedTestDefinition['tests'])) {
            throw new SetupException("Test definition tests are missing");
        }
        /** @var array<array<string>> $tests */
        $tests = $parsedTestDefinition['tests'];
        foreach ($tests as $test) {
            if (empty($test['sourceField']) || empty($test['destinationField'])) {
                throw new SetupException("Test definition tests are missing");
            }
        }
    }
}
