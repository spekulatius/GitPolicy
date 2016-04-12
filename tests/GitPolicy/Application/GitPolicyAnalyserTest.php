<?php

/**
 * Extend to expose: The way the GitPolicyAnalyser is built this class is required to expose all internals.
 *
 * Mainly we mock the input and expose the methods and outputs.
 */
class GitPolicyAnalyserMock extends \GitPolicy\Application\GitPolicyAnalyser {
    /**
     * holder for the printed output
     *
     * @var array
     */
    public $capturedOutput = [];

    /**
     * internal flag when the execution would have been stopped
     *
     * @var boolean
     */
    public $wasExited = false;

    /**
     * exposes the input values during the execution
     *
     * @return array $fields
     */
    public function getInputFields()
    {
        return $this->input;
    }

    /**
     * overwrite the preprocessed data of the input helper
     *
     * @param array $fields
     */
    public function setInputFields($fields)
    {
        $this->input = $fields;

        // run the analyse because we would do this next anyway
        $this->analyseContext();
    }

    /**
     * overwrite the output helper to capture the output the program would give
     *
     * @param string $message
     * @param string $messageType
     */
    protected function out($message, $messageType = null)
    {
        // empty messages have no value for the user.
        if (trim($message) == '' || $this->wasExited) {
            return;
        }

        // wrap message in tag to define the output
        if ($messageType != null) {
            $message = "<{$messageType}>".trim($message)."</{$messageType}>";
        }

        // yay! print it :)
        $this->capturedOutput[] = $message;

        // we really shouldn't continue if an error happens
        if ($messageType == 'error') {
            $this->capturedOutput[] = '<error>Stopping :/</error>';

            // usually this would mean we really stop, but to allow
            //  phpunit to finish proper we continue, just flag it as "was existed"
            $this->wasExited = true;
        }
    }

    /**
     * The following method is simply a nicer way to access the verifyPolicy method.
     *
     * @param array $baseConfig
     *
     * @return int
     */
    public function doRunVerifyPolicy($baseConfig)
    {
        // get the right section of the config for this
        $config = $this->getRefSpecificConfiguration($baseConfig, $this->input);

        // run the actual verification.
        $this->verifyPolicy($config, $this->input);

        // print the messages and signal that we're done.
        $this->printMessages($config, $this->input);
        $this->out('Done :)', 'good');
    }

    /**
     * The following method is just exposing the protected method of loadConfig.
     *
     * @param  string $filename
     *
     * @return array $baseConfig
     */
    public function loadConfigExposed($filename){
        return $this->loadConfig($filename);
    }
}

class GitPolicyAnalyserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * verifies the states which have been analysed
     *
     * @param GitPolicyAnalyserMock $analyser
     * @param array $shouldBeTrue
     * @param array $shouldBeFalse
     */
    protected function verifyStates(GitPolicyAnalyserMock $analyser, array $shouldBeTrue, array $shouldBeFalse)
    {
        // verify states
        $input = $analyser->getInputFields();
        $states = $input['context']['is'];
        foreach ($states as $field => $state) {
            if (in_array($field, $shouldBeTrue)) {
                $this->assertTrue($state, "Analyser: \'{$field}\' should be true.");
            }

            if (in_array($field, $shouldBeFalse)) {
                $this->assertFalse($state, "Analyser: \'{$field}\' should be false.");
            }
        }
    }

    /**
     * runs the analyse
     *
     * @param GitPolicyAnalyserMock $analyser
     * @param string $configFile
     */
    protected function runAnalyserBasedOnConfigBasedOnConfig(GitPolicyAnalyserMock $analyser, $configFile)
    {
        // get the config for this test
        $baseConfig = $analyser->loadConfigExposed($configFile);

        // check if there tree matcher is working as expected
        $analyser->doRunVerifyPolicy($baseConfig);
    }

    /**
     * assertStringContains
     *
     * @param string $needle
     * @param string $haystack
     * @param string $message
     */
    protected function assertStringContains($needle, $haystack, $message)
    {
        $this->assertTrue(
            strpos($haystack, $needle) !== false,
            $message
        );
    }

    /**
     * assertStringDoesnotContain
     *
     * @param string $needle
     * @param string $haystack
     * @param string $message
     */
    protected function assertStringDoesnotContain($needle, $haystack, $message)
    {
        $this->assertTrue(
            strpos($haystack, $needle) === false,
            $message
        );
    }

    /**
     * simulates a new tag being pushed
     *
     */
    public function testCreateGitTag()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/tags/1.2.3',
            'remote_sha' => '0000000000000000000000000000000000000000',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly
        $this->verifyStates(
            $analyser,
            ['tag', 'create'],
            ['branch', 'update', 'delete']
        );
    }

    /**
     * simulates a existing tag being overwritten
     *
     * should not be a problem ever - git blocks it.
     *
     */
    public function testUpdateGitTag()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/tags/1.2.3',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly
        $this->verifyStates(
            $analyser,
            ['tag', 'update'],
            ['branch', 'create', 'delete']
        );
    }

    /**
     * Simulates the delete of a tag on a remote
     *
     */
    public function testDeleteGitTag()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '0000000000000000000000000000000000000000',
            'local_ref' => '(deleted)',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly
        $this->verifyStates(
            $analyser,
            ['tag', 'delete'],
            ['branch', 'create', 'update']
        );
    }

    /**
     * simulates a new branch being pushed
     *
     */
    public function testCreateGitBranch()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/heads/branch-name',
            'remote_sha' => '0000000000000000000000000000000000000000',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly
        $this->verifyStates(
            $analyser,
            ['branch', 'create'],
            ['tag', 'update', 'delete']
        );
    }

    /**
     * simulated a branching being updated
     *
     */
    public function testUpdateGitBranch()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/heads/branch-name',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly
        $this->verifyStates(
            $analyser,
            ['branch', 'update'],
            ['tag', 'create', 'delete']
        );
    }

    /**
     * simulates the deleting of a branch on a remote
     *
     */
    public function testDeleteGitBranch()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '0000000000000000000000000000000000000000',
            'local_ref' => '(deleted)',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly
        $this->verifyStates(
            $analyser,
            ['branch', 'delete'],
            ['tag', 'create', 'update']
        );
    }

    /**
     * allowed to create new git tag
     *
     * @depends testCreateGitTag
     */
    public function testTagCreateGood()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/tags/1.2.3',
            'remote_sha' => '0000000000000000000000000000000000000000',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.non-strict.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * forbidden to create new git tag
     *
     * @depends testCreateGitTag
     */
    public function testTagCreateBad()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/tags/1.2.3',
            'remote_sha' => '0000000000000000000000000000000000000000',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict-tag.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Stopping', $completeOutput, 'This case should be stopping.');
        $this->assertStringDoesnotContain('Done', $completeOutput, 'This case should not end positiv.');
    }

    /**
     * allowed to update new git tag
     *
     * @depends testUpdateGitTag
     */
    public function testTagUpdateGood()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/tags/1.2.3',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.non-strict.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * forbidden to update new git tag
     *
     * @depends testUpdateGitTag
     */
    public function testTagUpdateBad()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/tags/1.2.3',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict-tag.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Stopping', $completeOutput, 'This case should be stopping.');
        $this->assertStringDoesnotContain('Done', $completeOutput, 'This case should not end positiv.');
    }

    /**
     * allowed to delete git tag
     *
     * @depends testDeleteGitTag
     */
    public function testTagDeleteGood()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '0000000000000000000000000000000000000000',
            'local_ref' => '(deleted)',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.non-strict.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * forbidden to delete new git tag
     *
     * @depends testDeleteGitTag
     */
    public function testTagDeleteBad()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '0000000000000000000000000000000000000000',
            'local_ref' => '(deleted)',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict-tag.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Stopping', $completeOutput, 'This case should be stopping.');
        $this->assertStringDoesnotContain('Done', $completeOutput, 'This case should not end positiv.');
    }

    /**
     * allowed to create new git branch
     *
     * @depends testCreateGitBranch
     */
    public function testBranchCreateGood()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/heads/branch-name',
            'remote_sha' => '0000000000000000000000000000000000000000',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.non-strict.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * forbidden to create new git branch
     *
     * @depends testCreateGitBranch
     */
    public function testBranchCreateBad()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/heads/branch-name',
            'remote_sha' => '0000000000000000000000000000000000000000',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Stopping', $completeOutput, 'This case should be stopping.');
        $this->assertStringDoesnotContain('Done', $completeOutput, 'This case should not end positiv.');
    }

    /**
     * allowed to update new git branch
     *
     * @depends testUpdateGitBranch
     */
    public function testBranchUpdateGood()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/heads/branch-name',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.non-strict.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * forbidden to update new git branch
     *
     * @depends testUpdateGitBranch
     */
    public function testBranchUpdateBad()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/heads/branch-name',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Stopping', $completeOutput, 'This case should be stopping.');
        $this->assertStringDoesnotContain('Done', $completeOutput, 'This case should not end positiv.');
    }

    /**
     * allowed to delete git branch
     *
     * @depends testDeleteGitBranch
     */
    public function testBranchDeleteGood()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '0000000000000000000000000000000000000000',
            'local_ref' => '(deleted)',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.non-strict.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * forbidden to delete new git branch
     *
     * @depends testDeleteGitBranch
     */
    public function testBranchDeleteBad()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '0000000000000000000000000000000000000000',
            'local_ref' => '(deleted)',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Stopping', $completeOutput, 'This case should be stopping.');
        $this->assertStringDoesnotContain('Done', $completeOutput, 'This case should not end positiv.');
    }

    /**
     * allowed to create new git tag with partial config
     *
     * @depends testCreateGitTag
     */
    public function testPartialConfigTagCreateGood()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/tags/1.2.3',
            'remote_sha' => '0000000000000000000000000000000000000000',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict-branch.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * forbidden to create new git tag with partial config
     *
     * @depends testCreateGitTag
     */
    public function testPartialConfigTagCreateBad()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/tags/1.2.3',
            'remote_sha' => '0000000000000000000000000000000000000000',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict-tag.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Stopping', $completeOutput, 'This case should be stopping.');
        $this->assertStringDoesnotContain('Done', $completeOutput, 'This case should not end positiv.');
    }

    /**
     * allowed to update new git tag with partial config
     *
     * @depends testUpdateGitTag
     */
    public function testPartialConfigTagUpdateGood()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/tags/1.2.3',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict-branch.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * forbidden to update new git tag with partial config
     *
     * @depends testUpdateGitTag
     */
    public function testPartialConfigTagUpdateBad()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/tags/1.2.3',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict-tag.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Stopping', $completeOutput, 'This case should be stopping.');
        $this->assertStringDoesnotContain('Done', $completeOutput, 'This case should not end positiv.');
    }

    /**
     * allowed to delete git tag with partial config
     *
     * @depends testDeleteGitTag
     */
    public function testPartialConfigTagDeleteGood()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '0000000000000000000000000000000000000000',
            'local_ref' => '(deleted)',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict-branch.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * forbidden to delete new git tag with partial config
     *
     * @depends testDeleteGitTag
     */
    public function testPartialConfigTagDeleteBad()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '0000000000000000000000000000000000000000',
            'local_ref' => '(deleted)',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict-tag.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Stopping', $completeOutput, 'This case should be stopping.');
        $this->assertStringDoesnotContain('Done', $completeOutput, 'This case should not end positiv.');
    }

    /**
     * allowed to create new git branch with partial config
     *
     * @depends testCreateGitBranch
     */
    public function testPartialConfigBranchCreateGood()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/heads/branch-name',
            'remote_sha' => '0000000000000000000000000000000000000000',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict-tag.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * forbidden to create new git branch with partial config
     *
     * @depends testCreateGitBranch
     */
    public function testPartialConfigBranchCreateBad()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/heads/branch-name',
            'remote_sha' => '0000000000000000000000000000000000000000',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict-branch.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Stopping', $completeOutput, 'This case should be stopping.');
        $this->assertStringDoesnotContain('Done', $completeOutput, 'This case should not end positiv.');
    }

    /**
     * allowed to update new git branch with partial config
     *
     * @depends testUpdateGitBranch
     */
    public function testPartialConfigBranchUpdateGood()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/heads/branch-name',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict-tag.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * forbidden to update new git branch with partial config
     *
     * @depends testUpdateGitBranch
     */
    public function testPartialConfigBranchUpdateBad()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/heads/branch-name',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict-branch.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Stopping', $completeOutput, 'This case should be stopping.');
        $this->assertStringDoesnotContain('Done', $completeOutput, 'This case should not end positiv.');
    }

    /**
     * allowed to delete git branch with partial config
     *
     * @depends testDeleteGitBranch
     */
    public function testPartialConfigBranchDeleteGood()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '0000000000000000000000000000000000000000',
            'local_ref' => '(deleted)',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict-tag.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * forbidden to delete new git branch with partial config
     *
     * @depends testDeleteGitBranch
     */
    public function testPartialConfigBranchDeleteBad()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '0000000000000000000000000000000000000000',
            'local_ref' => '(deleted)',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.strict-branch.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('Stopping', $completeOutput, 'This case should be stopping.');
        $this->assertStringDoesnotContain('Done', $completeOutput, 'This case should not end positiv.');
    }

    /**
     * checks the "after_push_messages" for creating a git tag
     *
     * @depends testCreateGitTag
     */
    public function testTagCreateMessage()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/tags/1.2.3',
            'remote_sha' => '0000000000000000000000000000000000000000',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.messages.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('custom-message-tag-tag', $completeOutput, 'Custom message for tag-tag is not displayed');
        $this->assertStringContains('custom-message-tag-create', $completeOutput, 'Custom message for tag-create is not displayed');
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('custom-message-tag-update', $completeOutput, 'Custom message for tag-update should not be displayed');
        $this->assertStringDoesnotContain('custom-message-tag-delete', $completeOutput, 'Custom message for tag-delete should not be displayed');
        $this->assertStringDoesnotContain('custom-message-branch-branch', $completeOutput, 'Custom message for branch-branch should not be displayed');
        $this->assertStringDoesnotContain('custom-message-branch-create', $completeOutput, 'Custom message for branch-create should not be displayed');
        $this->assertStringDoesnotContain('custom-message-branch-update', $completeOutput, 'Custom message for branch-update should not be displayed');
        $this->assertStringDoesnotContain('custom-message-branch-delete', $completeOutput, 'Custom message for branch-delete should not be displayed');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * checks the 'after_push_messages' for updating a git tag
     *
     * Should never actually happen.
     *
     * @depends testUpdateGitTag
     */
    public function testTagUpdateMessage()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/tags/1.2.3',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.messages.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('custom-message-tag-tag', $completeOutput, 'Custom message for tag-tag is not displayed');
        $this->assertStringContains('custom-message-tag-update', $completeOutput, 'Custom message for tag-update is not displayed');
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('custom-message-tag-create', $completeOutput, 'Custom message for tag-create should not be displayed');
        $this->assertStringDoesnotContain('custom-message-tag-delete', $completeOutput, 'Custom message for tag-delete should not be displayed');
        $this->assertStringDoesnotContain('custom-message-branch-branch', $completeOutput, 'Custom message for branch-branch should not be displayed');
        $this->assertStringDoesnotContain('custom-message-branch-create', $completeOutput, 'Custom message for branch-create should not be displayed');
        $this->assertStringDoesnotContain('custom-message-branch-update', $completeOutput, 'Custom message for branch-update should not be displayed');
        $this->assertStringDoesnotContain('custom-message-branch-delete', $completeOutput, 'Custom message for branch-delete should not be displayed');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * checks the "after_push_messages" for deleting a git tag
     *
     * @depends testDeleteGitTag
     */
    public function testTagDeleteMessage()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '0000000000000000000000000000000000000000',
            'local_ref' => '(deleted)',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/tags/1.2.3',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.messages.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('custom-message-tag-tag', $completeOutput, 'Custom message for tag-tag is not displayed');
        $this->assertStringContains('custom-message-tag-delete', $completeOutput, 'Custom message for tag-delete is not displayed');
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('custom-message-tag-create', $completeOutput, 'Custom message for tag-create should not be displayed');
        $this->assertStringDoesnotContain('custom-message-tag-update', $completeOutput, 'Custom message for tag-update should not be displayed');
        $this->assertStringDoesnotContain('custom-message-branch-branch', $completeOutput, 'Custom message for branch-branch should not be displayed');
        $this->assertStringDoesnotContain('custom-message-branch-create', $completeOutput, 'Custom message for branch-create should not be displayed');
        $this->assertStringDoesnotContain('custom-message-branch-update', $completeOutput, 'Custom message for branch-update should not be displayed');
        $this->assertStringDoesnotContain('custom-message-branch-delete', $completeOutput, 'Custom message for branch-delete should not be displayed');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * checks the "after_push_messages" for creating a git branch
     *
     * @depends testCreateGitBranch
     */
    public function testBranchCreateMessage()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/heads/branch-name',
            'remote_sha' => '0000000000000000000000000000000000000000',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.messages.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('custom-message-branch-branch', $completeOutput, 'Custom message for branch-branch should not be displayed');
        $this->assertStringContains('custom-message-branch-create', $completeOutput, 'Custom message for branch-create should not be displayed');
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('custom-message-branch-update', $completeOutput, 'Custom message for branch-update should not be displayed');
        $this->assertStringDoesnotContain('custom-message-branch-delete', $completeOutput, 'Custom message for branch-delete should not be displayed');
        $this->assertStringDoesnotContain('custom-message-tag-tag', $completeOutput, 'Custom message for tag-tag is not displayed');
        $this->assertStringDoesnotContain('custom-message-tag-create', $completeOutput, 'Custom message for tag-create is not displayed');
        $this->assertStringDoesnotContain('custom-message-tag-update', $completeOutput, 'Custom message for tag-update should not be displayed');
        $this->assertStringDoesnotContain('custom-message-tag-delete', $completeOutput, 'Custom message for tag-delete should not be displayed');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * checks the 'after_push_messages' for updating a git branch
     *
     * @depends testUpdateGitBranch
     */
    public function testBranchUpdateMessage()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '1234567891234567891234567891234567891234',
            'local_ref' => 'refs/heads/branch-name',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.messages.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('custom-message-branch-branch', $completeOutput, 'Custom message for branch-branch should not be displayed');
        $this->assertStringContains('custom-message-branch-update', $completeOutput, 'Custom message for branch-update should not be displayed');
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('custom-message-branch-create', $completeOutput, 'Custom message for branch-create should not be displayed');
        $this->assertStringDoesnotContain('custom-message-branch-delete', $completeOutput, 'Custom message for branch-delete should not be displayed');
        $this->assertStringDoesnotContain('custom-message-tag-tag', $completeOutput, 'Custom message for tag-tag is not displayed');
        $this->assertStringDoesnotContain('custom-message-tag-update', $completeOutput, 'Custom message for tag-update is not displayed');
        $this->assertStringDoesnotContain('custom-message-tag-create', $completeOutput, 'Custom message for tag-create should not be displayed');
        $this->assertStringDoesnotContain('custom-message-tag-delete', $completeOutput, 'Custom message for tag-delete should not be displayed');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }

    /**
     * checks the "after_push_messages" for deleting a git branch
     *
     * @depends testDeleteGitBranch
     */
    public function testBranchDeleteMessage()
    {
        // generate an instance of GitPolicyAnalyser using the mock
        $analyser = new GitPolicyAnalyserMock();


        // set the input
        $analyser->setInputFields([
            'local_sha' => '0000000000000000000000000000000000000000',
            'local_ref' => '(deleted)',
            'remote_sha' => '9876543219876543219876543219876543219876',
            'remote_ref' => 'refs/heads/branch-name',
        ]);


        // check if there analyser is working correctly (with the right config here)
        $this->runAnalyserBasedOnConfigBasedOnConfig(
            $analyser,
            './tests/templates/.messages.gitpolicy.yml'
        );

        // check the expectations on the output
        $completeOutput = implode("\n", $analyser->capturedOutput);
        $this->assertStringContains('custom-message-branch-branch', $completeOutput, 'Custom message for branch-branch should not be displayed');
        $this->assertStringContains('custom-message-branch-delete', $completeOutput, 'Custom message for branch-delete should not be displayed');
        $this->assertStringContains('Done', $completeOutput, 'This case should end positiv.');
        $this->assertStringDoesnotContain('custom-message-branch-create', $completeOutput, 'Custom message for branch-create should not be displayed');
        $this->assertStringDoesnotContain('custom-message-branch-update', $completeOutput, 'Custom message for branch-update should not be displayed');
        $this->assertStringDoesnotContain('custom-message-tag-tag', $completeOutput, 'Custom message for tag-tag is not displayed');
        $this->assertStringDoesnotContain('custom-message-tag-delete', $completeOutput, 'Custom message for tag-delete is not displayed');
        $this->assertStringDoesnotContain('custom-message-tag-create', $completeOutput, 'Custom message for tag-create should not be displayed');
        $this->assertStringDoesnotContain('custom-message-tag-update', $completeOutput, 'Custom message for tag-update should not be displayed');
        $this->assertStringDoesnotContain('Stopping', $completeOutput, 'This case should not be stopping.');
    }
}