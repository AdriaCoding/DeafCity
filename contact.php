<?php

require("src/autoload.php");

if (!empty($_POST)) $method = 'submit';
else $method="index";

app()->run(\DeafCity\Controllers\Contact::class, $method); 
