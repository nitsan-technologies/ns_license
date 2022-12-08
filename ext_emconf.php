<?php

$EM_CONF['ns_license'] = array (
  'title' => '[NITSAN] License Manager',
  'description' => 'Manage License(s) of your purchased T3Planet\'s premium TYPO3 products. Know more at documentation https://docs.t3planet.com/en/latest/License/LicenseActivation/Index.html',
  'category' => 'templates',
  'author' => 'Team NITSAN',
  'author_email' => 'sanjay@nitsan.in',
  'author_company' => 'NITSAN Technologies Pvt Ltd',
  'state' => 'stable',
  'version' => '1.8.2',
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
