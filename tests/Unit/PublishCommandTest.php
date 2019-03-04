<?php

namespace LaravelSabre\Tests\Unit;

use LaravelSabre\Tests\FeatureTestCase;

class PublishCommandTest extends FeatureTestCase
{
    public function test_command()
    {
        $this->artisan('laravelsabre:publish')->expectsOutput('Publishing complete.');
    }
}
