<?php

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['flysystem'] = \B13\DistributedFlysystem\FillFileAction::class . '::pullNonExistentFile';
