<?php

// TYPO3 Security Check
if (!defined('TYPO3')) {
    die('Access denied.');
}

$_EXTKEY = 'ns_license';

// Register backend stylesheets
$GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['ns_license'] = 'EXT:ns_license/Resources/Public/css/custom.css';
