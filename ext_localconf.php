<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

\Nemo64\Typo3Api\Hook\SqlSchemaHook::attach();