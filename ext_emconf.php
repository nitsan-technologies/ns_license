<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "ns_license".
 *
 * Auto generated 31-03-2026 16:31
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array (
  'title' => 'License Manager',
  'description' => 'Manage licenses of your TYPO3 templates and extensions purchased from T3Planet. Includes activation and license validation. Documentation available at https://docs.t3planet.com/en/latest/License/LicenseActivation/Index.html',
  'category' => 'templates',
  'version' => '14.2.2',
  'state' => 'stable',
  'uploadfolder' => false,
  'clearcacheonload' => false,
  'author' => 'Team T3Planet',
  'author_email' => 'info@t3planet.de',
  'author_company' => 'T3Planet',
  'constraints' => 
  array (
    'depends' => 
    array (
      'typo3' => '12.0.0-14.1.1',
      'extensionmanager' => '12.0.0-14.1.1',
    ),
    'conflicts' => 
    array (
    ),
    'suggests' => 
    array (
    ),
  ),
);

