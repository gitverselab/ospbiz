<?php

class Env {
    /**
     * Load environment variables from .env file
     * @param string $path Path to .env file
     */
    public static function load($path) {
        if (!file_exists($path)) {
            // If .env is missing, we check if variables are already set in the server environment
            // If strictly relying on .env, you can throw an exception here.
            return; 
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments starting with #
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Split by the first '='
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);

                $name = trim($name);
                $value = trim($value);

                // Remove surrounding quotes if present
                if (preg_match('/^"(.*)"$/', $value, $matches)) {
                    $value = $matches[1];
                } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                    $value = $matches[1];
                }

                // Set the environment variable if not already set
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}