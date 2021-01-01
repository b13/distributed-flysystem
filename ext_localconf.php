<?php

defined('TYPO3_MODE') or die();

$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['flysystem'] = \B13\DistributedFlysystem\FillFileAction::class . '::pullNonExistentFile';
