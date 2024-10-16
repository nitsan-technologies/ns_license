<?php

$EM_CONF['ns_license'] = array (
  'title' => 'License Manager',
  'description' => 'Manage License(s) of your purchased from T3Planet such as TYPO3 Templates and TYPO3 Extensions. Documentation & Free Support https://docs.t3planet.com/en/latest/License/LicenseActivation/Index.html',
  'category' => 'templates',
  'author' => 'Team T3Planet',
  'author_email' => 'sanjay@nitsan.in',
  'author_company' => 'T3Planet // NITSAN',
  'state' => 'stable',
  'version' => '1.9.5',
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
