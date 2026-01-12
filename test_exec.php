<?php
echo "<pre>";
echo "Testing exec...\n";
$output = shell_exec('ls -l');
var_dump($output);
?>