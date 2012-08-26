<?php
/*
 * PHPSH - An example about shellin', PHP and callbacks
 * Copyright (C) 2008 Víctor Román Archidona <contacto@victor-roman.es>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, see <http://www.gnu.org/licenses/>.
 */
class PHPShell
{
    protected $bufferOutput  = null;
    protected $consolePrompt = "phpsh (%path%)> ";
    private   $currentPath   = null;
    private   $registered_commands = array();

    /*
     * @fn __construct($prompt = null)
     * @param[in] string $prompt Prompt to be used
     *
     * This function is the entrypoint for the shell emulator
     */
    public function __construct($prompt = null)
    {
        $this->currentPath = getcwd();
        $this->setPrompt($prompt ? $prompt : $this->consolePrompt);

        $this->registerCommand("cd",   array($this, "_builtInCommandChdir"));
        $this->registerCommand("exit", array($this, "_builtInCommandExit"));
    }

    /*
     * @fn getCommand($input)
     * @brief From a given input, strips the first thing (the command)
     * @param[in] string $input Input to analyze
     * @return The first thing
     */
    function getCommand($input)
    {
        $input  = trim($input);
        $space_exists = strpos($input, " ");

        if ($space_exists)
            $input = substr($input, 0, $space_exists);

        return $input;
    }

    /*
     * @fn getParameters($input)
     * @brief From a given input, strips the parameters (all except first word)
     * @param[in] string $input Input to analyze
     * @return Every word, as an array, except the first one
     */
    function getParameters($input)
    {
        $input  = trim($input);
        $space_exists = strpos($input, " ");

        if ($space_exists) {
            $parameters = substr($input, strpos($input, " ") + 1);
            $parameters = explode(" ", $parameters);

            return $parameters;
        }

        return array();
    }

    /*
     * @fn Process($input)
     * @brief Event dispatcher. The core of the class
     * @param[in] string $input Input to process
     *
     * NOTES
     * -----
     *    1. First gets the command and parameters
     *    2. If command is not null:
     *       1. If is a system command, it gets the full path and execute
     *       2. Else, if is an internal command, runs it
     *       3. Otherwise runs the input as php code using eval()
     */
    public function Process($input)
    {
        $command = $this->getCommand($input);
        $params  = $this->getParameters($input);

        if (strlen($command)) {
            if ($this->isSystemCommand($command)) {
                $full_cmd_path = $this->getSystemCommandFullPath($command);
                $this->runSystemCommand($full_cmd_path, $params);
            } else if ($this->isCommand($command)) {
                $output = $this->runCommand($command, $params);

                if ($output)
                    $this->Write($output);
            } else {
                eval($input);
            }
        }
    }

    public function _builtInCommandChdir($parameters)
    {
        if ($parameters[0] != DIRECTORY_SEPARATOR)
            $newdir = realpath($this->currentPath.DIRECTORY_SEPARATOR.$parameters);
        else
            $newdir = $this->currentPath.DIRECTORY_SEPARATOR.$parameters;

        if (is_dir($newdir)) {
            chdir($newdir);
            $this->currentPath = getcwd();
        } else {
            $this->Write("Directory $parameters does not exists".PHP_EOL);
        }
    }

    public function _builtInCommandExit($parameters = null)
    {
        $exit_code = 0;

        if ($parameters[0] && is_numeric($parameters[0]))
            $exit_code = $parameters[0];

        exit($exit_code);
    }

    /**
     * @fn promptReplace()
     * @brief Replaces the macro appearances inside current prompt
     */
    private function promptReplace()
    {
        $prompt = str_replace("%path%", $this->currentPath, $this->consolePrompt);
        return $prompt;
    }

    /**
     * @fn setPrompt($prompt)
     * @brief sets the prompt to be displayed
     * @param[in] string $prompt prompt to be setted
     *
     * NOTES
     *    1. You can use the macro %path% to display the *current* path.
     */
    public function setPrompt($prompt)
    {
        $this->consolePrompt = $prompt;
    }

    /**
     * @fn getPrompt()
     * @brief Gets the current prompt
     */
    public function getPrompt()
    {
        return $this->consolePrompt;
    }

    /**
     * @fn waitInput()
     * @brief Waits until the user press the enter key, showing the prompt
     */
    public function waitInput()
    {
        $this->Write($this->promptReplace($this->consolePrompt));
        return $this->Read();
    }

    /**
     * @fn registerCommand($command, $callback)
     * @brief Register a command with an internal callback
     * @input[in] string $command Command name exported
     * @input[in] string $callback Function that the command will execute
     *
     * @return true if the command is correctly registered
     *
     * NOTES:
     *    1. Callback must be a function or an array(class,method) pair
     *    2. The command must be unique (not previously registered)
     */
    public function registerCommand($command, $callback)
    {
        $command = strtolower($command);

        if (!in_array($command, $this->registered_commands)) {
            $new_command = array("command" => $command,
                         "callback" => $callback);

            if (is_array($callback)) {
                list($_class, $_method) = $callback;

                if (!method_exists($_class, $_method)) {
                    $this->Write("Function callback: \"$_class->$_method\" for command: \"$command\" does not exists\n");
                    return false;
                }
            } else {
                if (!function_exists($callback)) {
                    $this->Write("Function callback: \"$callback\" for command: \"$command\" does not exists\n");
                    return false;
                }
            }

            $this->registered_commands[] = $new_command;
            return true;
        }
    }

    /**
     * @fn isCommand($command)
     * @brief Determines if $command was registered via registerCommand
     * @input[in] string $command Command to be checked
     *
     * @return true if $command was registered via registerCommand, false otherwise
     */
    public function isCommand($command)
    {
        foreach ($this->registered_commands as $current)
            if ($current['command'] == $command)
                return true;

        return false;
    }

    /**
     * @fn isSystemCommand($command)
     * @brief Determines if $command is from system or not
     * @input[in] string $command Command to be checked
     *
     * @return true if $command came from system, false otherwise
     */
    public function isSystemCommand($command)
    {
        $syspath = $_ENV["PATH"];

        foreach (explode(":", $syspath) as $path)
            if (is_executable($path.DIRECTORY_SEPARATOR.$command))
                return true;

        return false;
    }

    /**
     * @fn getSystemCommandFullPath($command)
     * @brief Gets the full (real) path from a system command
     * @input[in] string $command Command to obtain
     *
     * @return The full path of the given system command
     */
    public function getSystemCommandFullPath($command)
    {
        $syspath = $_ENV["PATH"];

        foreach ((array)explode(":", $syspath) as $path)
            if (is_executable($path.DIRECTORY_SEPARATOR.$command))
                return $path.DIRECTORY_SEPARATOR.$command;

        return null;
    }

    /**
     * @fn runSystemCommand($command, $parameters)
     * @brief Runs a system command with specified $parameters
     * @param[in] string $command Command to execute
     * @param[in] string $parameters Parameters passed to the command
     */
    public function runSystemCommand($command, $parameters = null)
    {
        $parameters = is_array($parameters) ? implode(" ", $parameters) : null;
        passthru("$command $parameters");
    }

    /**
     * @fn runCommand($command, $parameters)
     * @brief Runs a registered command with specified $parameters
     * @param[in] string $command Command to execute
     * @param[in] string $parameters Parameters passed to the command
     */
    public function runCommand($command, $parameters = null)
    {
        $callback = $this->getCommandCallback($command);


        if (is_array($callback)) {
            list($_class, $_method) = $callback;
            call_user_func_array(array($_class, $_method), $parameters);
        } else {
            call_user_func_array($callback, $parameters);
        }
    }

    /**
     * @fn getCommandCallback($command)
     * @brief Gets the internal callback from an internal command
     * @param[in] string $command Command to check
     */
    public function getCommandCallback($command)
    {
        foreach ($this->registered_commands as $current)
            if ($current['command'] == $command)
                return $current['callback'];

        return null;
    }

    /**
     * @fn Read
     * @brief Reads input from STDIN
     * @return string readed from STDIN, without \r nor \n
     */
    public static function Read()
    {
        $drop_chars = array("\n", "\r");

        $buffer = fread(STDIN, 1024);
        $buffer = str_replace($drop_chars, null, $buffer);

        return $buffer;
    }

    /**
     * @fn Write($text)
     * @brief Writes $text to STDOUT
     * @param[in] string $text Text to be written
     */
    public static function Write($text)
    {
        $len = strlen($text);
        fwrite(STDOUT, $text, $len);
    }
}
