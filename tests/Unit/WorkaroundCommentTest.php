<?php

namespace LaravelSabre\Tests\Unit;

use LaravelSabre\Tests\FeatureTestCase;

/**
 * Every workaround for engine or framework behaviour carries a comment naming its reason, so it can
 * be removed once the reason disappears (User Story 5 scenario 4, FR-025, constitution I).
 *
 * Adding a workaround means adding a row here. That is the point: the list is the inventory a
 * maintainer reads when upgrading sabre/dav or the framework.
 */
class WorkaroundCommentTest extends FeatureTestCase
{
    /**
     * File to the phrases its comments must contain.
     *
     * @return array<string, array<int, string>>
     */
    private function workarounds(): array
    {
        return [
            'src/Sabre/Sapi.php' => [
                'Upstream reason',
                'Sabre\DAV\Server::__construct',
                'Sabre\DAV\Server::start',
                'can be removed if sabre/dav',
            ],
            'src/Sabre/RequestFactory.php' => [
                'already read the body',
            ],
            'src/Sabre/ResponseFactory.php' => [
                'rather than trusted from the upstream docblock',
            ],
            'routes/routes.php' => [
                'Kept for parity with 1.x',
                'does not depend on this',
            ],
            'src/Http/Middleware/EnsureEnabled.php' => [
                'published under 1.x',
            ],
        ];
    }

    public function test_every_workaround_names_its_upstream_reason()
    {
        foreach ($this->workarounds() as $file => $phrases) {
            $path = dirname(__DIR__, 2).'/'.$file;
            $this->assertFileExists($path);

            $contents = (string) file_get_contents($path);

            foreach ($phrases as $phrase) {
                $this->assertStringContainsString(
                    $phrase,
                    $contents,
                    $file.' must explain its workaround: expected to find "'.$phrase.'"'
                );
            }
        }
    }

    public function test_the_response_sender_override_is_documented_as_intentional()
    {
        $contents = (string) file_get_contents(dirname(__DIR__, 2).'/src/Sabre/Sapi.php');

        $this->assertStringContainsString('Intentionally empty', $contents);
    }
}
