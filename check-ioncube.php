<?php
echo 'ionCube: ';
echo extension_loaded('ionCube Loader') ? '✅ ACTIVE' : '❌ NOT ACTIVE';
echo "\n";

echo 'Function _il_exec(): ';
echo function_exists('_il_exec') ? '✅ EXISTS' : '❌ NOT FOUND';
echo "\n";

$all = get_defined_functions();
echo in_array('_il_exec', $all['internal']) ? "_il_exec FOUND ✅\n" : "_il_exec NOT FOUND ❌\n";
