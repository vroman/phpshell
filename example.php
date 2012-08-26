<?php
/*
 * Example output:
 * user@machine ~/phpsh_test $ php phpshell.example.php 
 * phpsh (/home/user/phpsh_test)> ls 
 * another_dir 
 * my_file.txt 
 * phpshell.class.php 
 * phpshell.example.php 
 * phpsh (/home/user/phpsh_test)> show my_file.txt 
 * hello world 
 * phpsh (/home/user/phpsh_test)> mem_usage 
 * Memory used: 193.734375 mB 
 * phpsh (/home/user/phpsh_test)> cd another_dir 
 * phpsh (/home/user/phpsh_test/another_dir)> ls 
 * phpsh (/home/user/phpsh_test/another_dir)> cd .. 
 * phpsh (/home/user/phpsh_test)> exit 
 * user@machine ~/phpsh_test $ 
 */

# 1. Load the class 
require_once "phpshell.class.php"; 

# 2. Create an instance to it 

# 3. Some example commands: 
$phpsh = new PHPShell("phpsh (%path%)> "); 
$phpsh->registerCommand("show", "_Show"); 
$phpsh->registerCommand("mem_usage", "_DisplayUsedMemory"); 

# 4. Main loop (You can replace $phpsh->Process() with your own 
# event dispatcher. See phpsh.class.php for details 
for (;;) { 
        $input  = trim($phpsh->waitInput()); 
        $phpsh->Process($input); 
} 

# These functions are examples ones: 
function _Show($parameters = array()) 
{ 
        $file = $parameters; 

        if (!is_file($file)) { 
                WriteConsole("File $file does not exists".PHP_EOL); 
                return 1; 
        } 

        if (!is_readable($file)) { 
                WriteConsole("File $file is not readable".PHP_EOL); 
                return 1; 
        } 

        WriteConsole(file_get_contents($file)); 
        return 0; 
} 

function _DisplayUsedMemory() 
{ 
        $memory_used = memory_get_usage()/1024; 
        WriteConsole("Memory used: $memory_used mB".PHP_EOL); 
} 

function WriteConsole($text) 
{ 
        $len = strlen($text); 
        fwrite(STDOUT, $text, $len); 
} 
?> 