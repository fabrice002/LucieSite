<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Les tests écrits avec Pest s'appuient sur le TestCase déjà fourni par le
| starter kit. Les tests PHPUnit existants (tests/Feature/Auth, etc.) restent
| valides et continuent d'être exécutés par la même suite.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
