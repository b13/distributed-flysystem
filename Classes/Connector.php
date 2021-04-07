<?php
declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "distributed-flysystem" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\DistributedFlysystem;

use League\Flysystem\Filesystem;
use League\Flysystem\PhpseclibV2\SftpAdapter;
use League\Flysystem\PhpseclibV2\SftpConnectionProvider;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

class Connector
{

    /**
     * @param string|null $excludeNode
     * @return Filesystem[]
     */
    public function getConnections(string $excludeNode = null)
    {
        $allConfigurations = $this->getAllConfigurations();
        foreach ($allConfigurations as $nodeName => $configuration) {
            if ($excludeNode === $configuration['host'] || $excludeNode === $nodeName || $excludeNode === ($configuration['alias'] ?? '')) {
                continue;
            }

            try {
                $provider = new SftpConnectionProvider(
                    $configuration['host'] ?? null,
                    $configuration['username'] ?? null,
                    $configuration['password'] ?? null,
                    $configuration['privateKey'] ?? null,
                    $configuration['passphrase'] ?? null,
                    $configuration['port'] ?? 22,
                    $configuration['useAgent'] ?? false,
                    $configuration['timeout'] ?? 10,
                    $configuration['maxTries'] ?? 4,
                    $configuration['hostFingerprint'] ?? null
                );
                yield new Filesystem(
                    new SftpAdapter(
                        $provider,
                        $configuration['rootPath'],
                        PortableVisibilityConverter::fromArray([
                            'file' => [
                                'public' => 0662,
                                'private' => 0662,
                            ],
                            'dir' => [
                                'public' => 0775,
                                'private' => 0775,
                            ],
                        ])
                    )
                );
            } catch (\Throwable $e) {
                // try next one if connection was not successful
                continue;
            }
        }
    }

    protected function getAllConfigurations()
    {
        $configurations = [];
        foreach ($GLOBALS['TYPO3_CONF_VARS']['FILE']['flysystem']['nodes'] ?? [] as $nodeName => $configuration) {
            $defaultConfiguration = $GLOBALS['TYPO3_CONF_VARS']['FILE']['flysystem']['default'];
            $configurations[$nodeName] = array_replace_recursive($defaultConfiguration, $configuration);
        }
        return $configurations;
    }
}