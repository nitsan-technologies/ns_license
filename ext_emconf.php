<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "ns_license".
 *
 * Auto generated 09-12-2021 14:37
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array (
  'title' => '[NITSAN] License Management',
  'description' => 'Manage License(s) of your purchased T3Terminal\'s premium TYPO3 products. Know more at documentation.',
  'category' => 'templates',
  'author' => 'Team NITSAN',
  'author_email' => 'sanjay@nitsan.in',
  'author_company' => 'NITSAN Technologies Pvt Ltd',
  'state' => 'stable',
  'version' => '1.2.2',
  'constraints' => 
  array (
    'depends' => 
    array (
      'typo3' => '8.0.0-11.5.99',
    ),
    'conflicts' => 
    array (
    ),
    'suggests' => 
    array (
    ),
  ),
  'autoload' => 
  array (
    'classmap' => 
    array (
      0 => 'Classes/',
    ),
  ),
  'uploadfolder' => false,
  'clearcacheonload' => false,
);

