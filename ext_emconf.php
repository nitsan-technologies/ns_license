<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "ns_license".
 *
 * Auto generated 04-10-2021 13:10
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = [
  'title' => '[NITSAN] License Management',
  'description' => 'Manage License(s) of your purchased T3Terminal\'s premium TYPO3 products. Know more at documentation.',
  'category' => 'templates',
  'author' => 'Team NITSAN',
  'author_email' => 'sanjay@nitsan.in',
  'author_company' => 'NITSAN Technologies Pvt Ltd',
  'state' => 'stable',
  'version' => '1.2.0',
  'constraints' =>
  [
    'depends' =>
    [
      'typo3' => '8.0.0-11.5.99',
    ],
    'conflicts' =>
    [
    ],
    'suggests' =>
    [
    ],
  ],
  'autoload' =>
  [
    'classmap' =>
    [
      0 => 'Classes/',
    ],
  ],
  'uploadfolder' => false,
  'createDirs' => null,
  'clearcacheonload' => false,
];
