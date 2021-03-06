<?php

/**
 * Created by PhpStorm.
 * User: ian, Cryotech
 * Date: 8/19/16
 * Time: 9:52 AM
 */
set_include_path(realpath($_SERVER['DOCUMENT_ROOT']) . '/assets/php');
require_once 'Database.php';

abstract class Puzzle
{
    protected static $type = null;
    protected static $generator_path = null;
    protected static $command = null;

    public function __construct()
    {

    }

    public function createNewPuzzleTable()
    {
        $sql = "CREATE TABLE " . static::$type . " (username VARCHAR (38) NOT NULL UNIQUE,
                                                    datacache VARCHAR (32000),
                                                    level INTEGER,
                                                    maxlevel INTEGER ,
                                                    PRIMARY KEY (username));";
        $stmt = Database::connect()->prepare($sql);
        return $stmt->execute();
    }

    public function getPuzzleData(Account $user, $level = null)
    {
        if (strlen($user->getToken()) < 5) throw new Exception("User password combo or token not known");
        $cache = $this->getCachedLevelData($user);
        if (!$cache)
        {
            //add new user
            $this->createPuzzleUser($user, -1, 0);
            $cache = $this->getCachedLevelData($user);
        }
        if ($level > $cache['maxlevel'] or $level < 0) throw new Exception("Level not yet available"); // User not found or user attempting to access level higher than maxlevel
        if ($level === null) $level = $cache['maxlevel']; // Default to max level if none specified

        if ($cache['level'] != $level or $cache['datacache'] === null)
        {
            $root = realpath($_SERVER['DOCUMENT_ROOT']); // Website root
            $output_file = $user->getUsername() . $level . '_' . static::$type . "_output.txt"; // e.g. amadeus10_sudoku_output.txt
            $full_command = static::$command . ' ' . $root . '/' . static::$generator_path . ' ' . $output_file . ' ' .
                " generate " . $user->getUsername() . ' ' . $level; // exec root\generator.exe output.txt generate amadeus 10
            exec($full_command); // Path Output_File User Level
            $file = getcwd() . '/' . $output_file; // e.g. Current_dir\amadeus10_sudoku_output.txt
            $handle = fopen($file, "r");
            if ($handle)
            {
                $data = fread($handle, filesize($file));

                fclose($handle);
                unlink($file);
            }
            else
                throw new Exception("Output file does not properly exist or generator failed.");

            $sql = "UPDATE " . static::$type . " SET datacache=:datacache, level=:level WHERE username=:username;";
            $stmt = Database::connect()->prepare($sql);
            $stmt->bindParam(":datacache", $data);
            $stmt->bindParam(":level", $level);
            $username = $user->getUsername();
            $stmt->bindParam(":username", $username);
            $stmt->execute();
            $cache = $this->getCachedLevelData($user);
        }
        return $cache;
    }

    protected function incrementMaxLevel(Account $user)
    {
        $sql = "UPDATE " . static::$type . " SET maxlevel=maxlevel+1 WHERE username=:user;";
        $stmt = Database::connect()->prepare($sql);
        $username = $user->getUsername();
        $stmt->bindParam(":user", $username);
        return $stmt->execute();
    }

    protected function getCachedLevelData(Account $user)
    {
        $sql = "SELECT * FROM " . static::$type . " WHERE username=:user LIMIT 1;";
        $stmt = Database::connect()->prepare($sql);
        $username = $user->getUsername();
        $stmt->bindParam(":user", $username);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function verifySolution(Account $user, $solution)
    {
        $cache = $this->getCachedLevelData($user);
        if (!$cache) throw new Exception("User " . $user->getUsername() . " does not exist.");
        if ($cache['level'] === null) return false;

        $root = realpath($_SERVER['DOCUMENT_ROOT']); // Website root
        $verify_file = $user->getUsername() . $cache['level'] . '_' . static::$type . "_verify.txt"; // e.g. amadeus10_sudoku_verify.txt
        $full_command = static::$command . ' ' . $root . '/' . static::$generator_path . ' ' . $verify_file . ' ' .
            " verify " . $user->getUsername() . ' ' . $cache['level'] . ' ' . $solution; // exec root\generator.exe verify.txt verify amadeus 10 solution
        exec($full_command); // Path Output_File User Level
        $file = getcwd() . '/' . $verify_file; // e.g. Current_dir\amadeus10_sudoku_output.txt
        $handle = fopen($file, "r");
        if ($handle)
        {
            $data = fread($handle, filesize($file));
            $data = strstr($data, PHP_EOL, true);

            fclose($handle);
            unlink($file);
        }
        else
            throw new Exception("Output file does not properly exist or generator failed.");

        return (strcmp($data, "true") == 0 ? true : false); // Returns true if file reads "true", else returns false
    }

    public function getTopPlayers($number)
    {
        if (is_int($number))
        {
            $sql = "SELECT username, maxlevel FROM " . static::$type . " ORDER BY maxlevel DESC LIMIT " . $number;
            $stmt = Database::connect()->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC); // Key/ value pairs only
        }
        return false;
    }

    public function createPuzzleUser(Account $user, $level = 0, $maxlevel = 0, $data = null)
    {
        $sql = "INSERT INTO " . static::$type . " (username, datacache, level, maxlevel) VALUES (:user, :data, :level, :mlevel) ON DUPLICATE KEY UPDATE username=:user, datacache=:data, level=:level, maxlevel=:mlevel;";
        $stmt = Database::connect()->prepare($sql);
        $username = $user->getUsername();
        $stmt->bindParam(":user", $username);
        $stmt->bindParam(":level", $level);
        $stmt->bindParam(":mlevel", $maxlevel);
        $stmt->bindParam(":data", $data);
        $stmt->execute();
    }
}
