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

use TYPO3\CMS\Core\Resource\Event\AfterFileAddedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileContentsSetEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileCopiedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileCreatedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileDeletedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileMovedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileRenamedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileReplacedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFolderAddedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFolderCopiedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFolderDeletedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFolderMovedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFolderRenamedEvent;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Actively pushes FAL-based files to other nodes.
 */
class Distributor
{
    /**
     * @var Connector
     */
    protected $connector;

    public function __construct(Connector $connector = null)
    {
        $this->connector = $connector ?? GeneralUtility::makeInstance(Connector::class);
    }

    public function addFile(AfterFileAddedEvent $event): void
    {
        $this->writeFileToRemotes($event->getFile());
    }

    public function updateContent(AfterFileContentsSetEvent $event): void
    {
        $this->writeFileToRemotes($event->getFile());
    }

    public function createFile(AfterFileCreatedEvent $event): void
    {
        // @todo: should we do something about this?
    }

    public function deleteFile(AfterFileDeletedEvent $event): void
    {
        $this->deleteFileFromRemotes($event->getFile());
    }

    public function copyFile(AfterFileCopiedEvent $event): void
    {
        $this->writeFileToRemotes($event->getNewFile());
    }

    public function moveFile(AfterFileMovedEvent $event): void
    {
        $file = $event->getFile();
        $sourceFolder = $event->getOriginalFolder();
        $sourcePath = trim($sourceFolder->getPublicUrl(), '/') . '/' . $file->getName();
        $newFilePath = $file->getForLocalProcessing(false);
        $this->moveResourceInRemotes($sourcePath, $newFilePath);
    }

    public function renameFile(AfterFileRenamedEvent $event): void
    {
        $file = $event->getFile();
        $folder = trim($file->getParentFolder()->getPublicUrl(), '/') . '/';
        $sourcePath = $folder . $file->getName();
        $newFilePath = $folder . $event->getTargetFileName();
        $this->moveResourceInRemotes($sourcePath, $newFilePath);
    }

    public function replaceFile(AfterFileReplacedEvent $event): void
    {
        $file = $event->getFile();
        $newFilePath = $event->getLocalFilePath();
        $newFilePath = PathUtility::stripPathSitePrefix($newFilePath);
        foreach ($this->connector->getConnections($_SERVER['SERVER_ADDR']) as $connection) {
            $connection->write('/' . ltrim($newFilePath, '/'), $file->getContents());
        }
    }

    public function moveFolder(AfterFolderMovedEvent $event): void
    {
        $this->moveResourceInRemotes($event->getFolder()->getPublicUrl(), $event->getTargetFolder()->getPublicUrl());

    }

    public function copyFolder(AfterFolderCopiedEvent $event): void
    {
        $folderPath = $event->getFolder()->getPublicUrl();
        $targetPath = $event->getTargetFolder()->getPublicUrl();
        foreach ($this->connector->getConnections($_SERVER['SERVER_ADDR']) as $connection) {
            $connection->copy($folderPath, $targetPath);
        }
    }

    public function renameFolder(AfterFolderRenamedEvent $event): void
    {
        // @todo
    }

    public function deleteFolder(AfterFolderDeletedEvent $event): void
    {
        $folderPath = $event->getFolder()->getPublicUrl();
        foreach ($this->connector->getConnections($_SERVER['SERVER_ADDR']) as $connection) {
            $connection->deleteDirectory($folderPath);
        }
    }

    public function addFolder(AfterFolderAddedEvent $event): void
    {
        $folderPath = $event->getFolder()->getPublicUrl();
        foreach ($this->connector->getConnections($_SERVER['SERVER_ADDR']) as $connection) {
            $connection->createDirectory($folderPath);
        }
    }


    protected function writeFileToRemotes(FileInterface $file): void
    {
        $fullFilePath = $file->getForLocalProcessing(false);
        $relativePath = PathUtility::stripPathSitePrefix($fullFilePath);
        foreach ($this->connector->getConnections($_SERVER['SERVER_ADDR']) as $connection) {
            $connection->write('/' . ltrim($relativePath, '/'), $file->getContents());
        }
    }

    protected function deleteFileFromRemotes(FileInterface $file): void
    {
        $fullFilePath = $file->getForLocalProcessing(false);
        $relativePath = PathUtility::stripPathSitePrefix($fullFilePath);
        foreach ($this->connector->getConnections($_SERVER['SERVER_ADDR']) as $connection) {
            $connection->delete('/' . ltrim($relativePath, '/'));
        }
    }

    protected function moveResourceInRemotes(string $from, string $target): void
    {
        $from = PathUtility::stripPathSitePrefix($from);
        $from = '/' . ltrim($from, '/');
        $target = PathUtility::stripPathSitePrefix($target);
        $target = '/' . ltrim($target, '/');
        foreach ($this->connector->getConnections($_SERVER['SERVER_ADDR']) as $connection) {
            if ($connection->fileExists($from)) {
                $connection->move($from, $target);
            }
        }
    }
}
