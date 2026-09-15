<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature', 'Integration');
uses(RefreshDatabase::class)->in('Feature', 'Integration');