<?php

// scripts/check-lang.php
$en = require __DIR__ . '/../src/lang/en.php';
$fr = require __DIR__ . '/../src/lang/fr.php';
foreach (array_diff_key($en, $fr) as $k => $_) echo "missing in fr: $k\n";
foreach (array_diff_key($fr, $en) as $k => $_) echo "unused in fr:  $k\n";