<?php
/**
 * Created by PhpStorm.
 * User: Cryotech
 * Date: 8/28/2016
 * Time: 12:44 AM
 */
require_once realpath($_SERVER['DOCUMENT_ROOT']) . '/assets/php/StdHeader.php';
require_once 'TestPuzzle.php';

echo '<p>Testing...</p>';

$account = new Account("Amadeus", "NotAGoodPassword", "email@email.ru");
echo $account->getUsername() . ' ' . $account->getEmail() . ' ' . $account->getToken() . "<br />";

$gen = new TestPuzzle();
$gen->createNewPuzzleTable();
$gen->createPuzzleUser($account, 0, 10, null); // Generate sample user

$data = $gen->getPuzzleData($account, 0);

//var_dump($gen->getPuzzleData($account, -1));
//var_dump($gen->getPuzzleData($account, 99));

echo '<br />';

echo strstr($data['datacache'], PHP_EOL); // Newlines preserved? // Yes.
$account->removeFromDatabase();

echo '<br />';

$account = new Account("highscore", "AlsoABadPassword", "email@email.ca");

$gen->createPuzzleUser($account, 0, 1000, null); // Test top players
$gen->getPuzzleData($account);

var_dump($top = $gen->getTopPlayers(10));

echo '<br />';

echo $top[0]['username'];
//echo $top[0][0];

echo '<br />';

foreach ($top as $score)
    echo $score['username'] . ' - ' . $score['maxlevel'] . '<br />';

echo '<br />';

var_dump($gen->verifySolution($account, "blue")); // GenTest.exe will output true if solution is "blue"
var_dump($gen->verifySolution($account, "red")); // GenTest.exe will output false if solution is anything else
$account->removeFromDatabase();

echo '<br />';

echo '<p>Done. (verify ' .  microtime() . ')</p>';