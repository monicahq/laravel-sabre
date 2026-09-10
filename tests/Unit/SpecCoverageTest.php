<?php

namespace LaravelSabre\Tests\Unit;

use LaravelSabre\Tests\FeatureTestCase;

/**
 * Every acceptance scenario in the specification must have a test, and a maintainer must be able to
 * find it (User Story 5 scenario 2, FR-027).
 *
 * Adding a scenario to the specification without adding a row here fails this test.
 */
class SpecCoverageTest extends FeatureTestCase
{
    private const SPEC = __DIR__.'/../../specs/001-rebuild-sabre-adapter/spec.md';

    /**
     * Scenario identifier to the test that drives it.
     *
     * @return array<string, string>
     */
    private function coverage(): array
    {
        return [
            'US1-1' => 'tests/Integration/ServeDavTest.php::test_propfind_on_a_registered_principal_returns_the_engine_multistatus',
            'US1-2' => 'tests/Integration/MethodCoverageTest.php::test_the_method_reaches_the_engine',
            'US1-3' => 'tests/Integration/RequestFidelityTest.php::test_the_engine_sees_the_request_as_middleware_left_it',
            'US1-4' => 'tests/Integration/StreamingTest.php::test_a_streamed_body_is_delivered_complete',
            'US1-5' => 'tests/Integration/ServeDavTest.php::test_a_get_with_nothing_registered_returns_the_engine_not_implemented_answer',
            'US1-6' => 'tests/Integration/IsolationTest.php::test_a_thousand_alternating_requests_each_see_only_their_own_identity',
            'US1-7' => 'tests/Integration/RouteRegistrationTest.php::test_the_endpoint_url_is_generated_from_the_route_name',
            'US2-1' => 'tests/Integration/AccessRuleTest.php::test_a_denied_request_gets_403_and_touches_no_provider',
            'US2-2' => 'tests/Integration/AccessRuleTest.php::test_an_admitted_request_proceeds_to_the_engine',
            'US2-3' => 'tests/Integration/AccessRuleTest.php::test_requests_are_admitted_when_no_rule_is_registered',
            'US2-4' => 'tests/Integration/AuthenticationTest.php::test_a_signed_in_user_is_identified_as_a_principal',
            'US2-5' => 'tests/Integration/AuthenticationTest.php::test_an_anonymous_request_is_challenged_with_the_configured_realm',
            'US2-6' => 'tests/Integration/AccessRuleTest.php::test_a_rule_that_throws_is_not_treated_as_admitted',
            'US2-7' => 'tests/Integration/AuthenticationTest.php::test_a_registered_principal_mapping_is_used',
            'US2-8' => 'tests/Unit/PrincipalResolverTest.php::test_the_guard_is_selectable',
            'US3-1' => 'tests/Integration/EndpointPlacementTest.php::test_the_default_path_answers_without_any_manual_registration',
            'US3-2' => 'tests/Integration/EndpointPlacementTest.php::test_a_changed_path_moves_the_endpoint_and_the_href_base',
            'US3-3' => 'tests/Integration/EndpointPlacementTest.php::test_a_configured_domain_restricts_where_the_endpoint_answers',
            'US3-4' => 'tests/Integration/MasterSwitchTest.php::test_every_path_under_the_endpoint_is_404_when_disabled',
            'US3-5' => 'tests/Integration/ConfigPublishingTest.php::test_an_edited_setting_takes_effect',
            'US3-6' => 'tests/Integration/CustomMiddlewareTest.php::test_a_custom_middleware_runs_for_dav_requests',
            'US4-1' => 'tests/Compatibility/ReplayTest.php::test_the_recording_replays',
            'US4-2' => 'tests/Unit/RemovedSurfaceTest.php::test_every_removed_element_is_named_in_the_migration_guide',
            'US4-3' => 'tests/Integration/LegacyConfigTest.php::test_a_config_file_published_under_1x_still_works_and_gains_the_new_defaults',
            'US4-4' => 'tests/Unit/RemovedSurfaceTest.php::test_calling_a_removed_accessor_fails_immediately',
            'US5-1' => 'tests/Unit/SupportedMatrixTest.php::test_composer_and_the_workflow_declare_the_same_matrix',
            'US5-2' => 'tests/Unit/SpecCoverageTest.php::test_every_acceptance_scenario_has_a_test',
            'US5-3' => 'tests/Unit/DocumentationTest.php::test_the_readme_documents_the_whole_public_surface',
            'US5-4' => 'tests/Unit/WorkaroundCommentTest.php::test_every_workaround_names_its_upstream_reason',
            'US5-5' => 'tests/Unit/RegistryIsolationTest.php::test_two_applications_in_one_process_keep_separate_registrations',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function scenarioIdentifiers(): array
    {
        $spec = (string) file_get_contents(self::SPEC);
        $parts = preg_split('/^### User Story (\d+)/m', $spec, -1, PREG_SPLIT_DELIM_CAPTURE);
        $this->assertIsArray($parts);

        $identifiers = [];
        for ($i = 1; $i < count($parts); $i += 2) {
            $story = $parts[$i];
            $body = $parts[$i + 1];

            if (preg_match('/\*\*Acceptance Scenarios\*\*:\n\n(.*?)\n---/s', $body, $matches) !== 1) {
                continue;
            }

            foreach (preg_split('/\n(?=\d+\. )/', trim($matches[1])) ?: [] as $scenario) {
                if (preg_match('/^(\d+)\. /', $scenario, $number) === 1) {
                    $identifiers[] = 'US'.$story.'-'.$number[1];
                }
            }
        }

        return $identifiers;
    }

    public function test_every_acceptance_scenario_has_a_test()
    {
        $coverage = $this->coverage();
        $scenarios = $this->scenarioIdentifiers();

        $this->assertNotEmpty($scenarios, 'no acceptance scenarios were found in the specification');

        foreach ($scenarios as $scenario) {
            $this->assertArrayHasKey(
                $scenario,
                $coverage,
                'acceptance scenario '.$scenario.' has no test mapped to it'
            );
        }

        $this->assertSame(
            [],
            array_diff(array_keys($coverage), $scenarios),
            'the coverage map names scenarios that are no longer in the specification'
        );
    }

    public function test_every_mapped_test_exists()
    {
        foreach ($this->coverage() as $scenario => $target) {
            [$file, $method] = explode('::', $target);
            $path = __DIR__.'/../../'.$file;

            $this->assertFileExists($path, $scenario.' points at a missing file');
            $this->assertStringContainsString(
                'function '.$method.'(',
                (string) file_get_contents($path),
                $scenario.' points at a missing test method: '.$method
            );
        }
    }

    public function test_http_behaviour_is_driven_through_the_real_route()
    {
        $httpScenarios = ['US1-1', 'US1-2', 'US1-3', 'US1-4', 'US1-5', 'US1-6', 'US2-1', 'US2-4', 'US3-1', 'US3-4', 'US4-1'];
        $coverage = $this->coverage();

        foreach ($httpScenarios as $scenario) {
            $file = explode('::', $coverage[$scenario])[0];

            $this->assertMatchesRegularExpression(
                '#^tests/(Integration|Compatibility)/#',
                $file,
                $scenario.' reaches HTTP and must be driven through the real route'
            );
        }
    }
}
