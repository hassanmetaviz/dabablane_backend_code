<?php
echo "<pre>";
echo shell_exec("which ffmpeg");
echo "\n";
echo shell_exec("ffmpeg -version");
echo "</pre>";
?>