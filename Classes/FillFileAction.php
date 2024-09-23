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

use League\Flysystem\UnableToRetrieveMetadata;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Check all other nodes if the requested file exists, and pull it from the first node
 * that contains this file.
 */
class FillFileAction
{
    /**
     * @var Connector
     */
    protected $connector;

    public function __construct(Connector $connector = null)
    {
        $this->connector = $connector ?? GeneralUtility::makeInstance(Connector::class);
    }

    public function pullNonExistentFile(ServerRequestInterface $request): ResponseInterface
    {
        $requestedFile = $request->getAttribute('normalizedParams')->getSiteScript();
        $requestedFile = $this->checkRequestedFileName($requestedFile);
        if (str_contains($requestedFile, '?')) {
            [$requestedFile] = explode($requestedFile, '?');
        }
        $absoluteFile = Environment::getPublicPath() . '/' . $requestedFile;
        if (file_exists($absoluteFile)) {
            return $this->deliverLocalFile($absoluteFile);
        }
        try {
            foreach ($this->connector->getConnections($_SERVER['SERVER_ADDR']) as $fileSystem) {
                if (!$fileSystem || !$fileSystem->fileExists($requestedFile)) {
                    continue;
                }
                try {
                    $lastModified = $fileSystem->lastModified($requestedFile);
                } catch (UnableToRetrieveMetadata $e) {
                    $lastModified = null;
                }
                $this->persistFile($absoluteFile, $fileSystem->read($requestedFile), $lastModified);
                // Need to be handled as stream, otherwise the file isn't shown (e.g. as image) in the first place.
                return new Response(
                    $fileSystem->readStream($requestedFile),
                    200,
                    ['Content-Type' => $fileSystem->mimeType($requestedFile)]
                );
            }
        } catch (\Throwable $e) {
            // Ensure not throw an PHP error when serving a non-existant file
        }
        return (new Response())->withStatus(404);
    }

    protected function persistFile($targetPath, $contents, $lastModified)
    {
        GeneralUtility::mkdir_deep(dirname($targetPath));
        GeneralUtility::writeFile($targetPath, $contents);
        // If a last modified is given, try to set this with touch()
        if (file_exists($targetPath) && $lastModified) {
            @touch($targetPath, $lastModified);
        }
    }

    protected function deliverLocalFile(string $absoluteFile): ResponseInterface
    {
        $mimeTypeDetector = new FinfoMimeTypeDetector();
        $fileContents = file_get_contents($absoluteFile);
        $mimeType = $mimeTypeDetector->detectMimeType($absoluteFile, $fileContents);
        return new Response($fileContents, 200, ['Content-Type' => $mimeType]);
    }

    /**
     * Find the actual filename, if versionNumberInFilename === embed or querystring is used.
     *
     * @param string $originalFileName
     * @return string
     */
    protected function checkRequestedFileName(string $originalFileName): string
    {
        // Remove a possible query string
        if (str_contains($originalFileName, '?')) {
            [$originalFileName] = explode('?', $originalFileName);
        }
        $originalBaseFileName = basename($originalFileName);
        $baseFileName = $originalBaseFileName;
        if (($GLOBALS['TYPO3_CONF_VARS']['FE']['versionNumberInFilename'] ?? '') === 'embed' || ($GLOBALS['TYPO3_CONF_VARS']['BE']['versionNumberInFilename'] ?? false) === true) {
            $baseFileName = preg_replace('/^(.+)\.(\d+)\.(php|js|css|png|jpg|gif|gzip)$/', '$1.$3', $originalBaseFileName);
        }
        if (!empty($baseFileName) && $baseFileName !== $originalBaseFileName) {
            return str_replace('/' . $originalBaseFileName, '/' . $baseFileName, $originalFileName);
        }
        return $originalFileName;
    }
}
