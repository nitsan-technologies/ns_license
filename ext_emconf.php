<?php

$EM_CONF['ns_license'] = array(
  'title' => 'TYPO3 License Manager',
  'description' => 'Manage licenses of your TYPO3 templates and extensions purchased from T3Planet. Includes activation and license validation. Documentation available at https://docs.t3planet.com/en/latest/License/LicenseActivation/Index.html',
  'category' => 'templates',
  'author' => 'Team T3Planet',
  'author_email' => 'info@t3planet.de',
  'author_company' => 'T3Planet',
  'state' => 'stable',
  'version' => '13.0.10',
  'constraints' =>
    array(
      'depends' =>
        array(
          'typo3' => '12.0.0-13.9.99',
        ),
      'conflicts' =>
        array(
      ),
      'suggests' =>
        array(
      ),
    ),
  'autoload' =>
    array(
      'classmap' =>
      array(
        0 => 'Classes/',
      ),
    ),
  'uploadfolder' => false,
  'clearcacheonload' => false,
);
