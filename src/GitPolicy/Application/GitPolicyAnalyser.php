<?php

namespace GitPolicy\Application;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class GitPolicyAnalyser extends Application
{
    /**
     * @var array
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * Name it.
     */
    public function __construct()
    {
        parent::__construct('GitPolicy hook analyser');
    }

    /**
     * GitPolicy' Brain.
     *
     * The following methods are a mix of functional patterns and OO patterns. It might take a bit to get the grasp
     * but once get the idea it will all be logical and show its immutable design.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    public function doRun(InputInterface $input = null, OutputInterface $output = null)
    {
        // little prep, add some context to the set input: $this->input and "welcome" :)
        $this->prepEnvironment($input, $output);
        $this->analyseContext();
        $this->out('Checking GitPolicy', 'good');

        // get the right section of the config for this
        $config = $this->getRefSpecificConfiguration($this->loadConfig(), $this->input);

        // run the actual verification - if we are failing here - lets stop here.
        if ($this->verifyPolicy($config, $this->input)) {
            // print the messages and signal that we're done.
            $this->printMessages($config, $this->input);
            $this->out('Done :)', 'good');

            exit(0);
        }

        // I'm probably just too stupid to know how to exit a PHP application console in a git hook proper.
        $tempFile = fopen('./.tmp-endgp', 'w');
        fwrite($tempFile, 'failed :(');
        fclose($tempFile);

        exit(1);
    }

    /**
     * analyses the given attempt to push.
     */
    protected function analyseContext()
    {
        $this->input['context'] = [
            // type and the name of the remote ref as a structure for the following processes
            'ref_type' => (strpos($this->input['remote_ref'], 'refs/tags/') === 0) ? 'tag' : 'branch',
            'ref_name' => preg_replace('"refs/.+/"', '', $this->input['remote_ref']),
            'refs_different' => ($this->input['local_ref'] != $this->input['remote_ref']),

            // easy to match list of states
            'is' => [
                'tag' => (strpos($this->input['remote_ref'], 'refs/tags/') === 0),
                'branch' => (strpos($this->input['remote_ref'], 'refs/heads/') === 0),
                'create' => ($this->input['remote_sha'] == '0000000000000000000000000000000000000000'),
                'update' => (
                    $this->input['local_sha'] != '0000000000000000000000000000000000000000' &&
                    $this->input['remote_sha'] != '0000000000000000000000000000000000000000'
                ),
                'delete' => (
                    $this->input['local_ref'] == '(deleted)' ||
                    $this->input['local_sha'] == '0000000000000000000000000000000000000000'
                ),
            ],
        ];

        // @TODO add more extensive checks in here. For example go through the differences between the two hashs and
        //  verify all commit messages are fine by gitpolicy.
    }

    /**
     * Returns the specific section of the configuration for this push.
     *
     * E.g. if a tag is pushed/deleted returns the 'tag' section from the config.
     *
     * Fallback is always an empty array.
     *
     * @param array $config
     * @param array $push
     *
     * @return array
     */
    protected function getRefSpecificConfiguration(array $config, array $push)
    {
        return
            // no intention to process anything else than tags and branches for now ;)
            !(in_array($push['context']['ref_type'], ['tag', 'branch'])) ? [] :

            // get the right section of the config for this
            (array_key_exists($push['context']['ref_type'], $config)) ? $config[$push['context']['ref_type']] : [];
    }

    /**
     * verifies if any of the set policy parameters is/was violated.
     *
     * This method is used for some functional programming demonstration as well.
     *
     * @param array $config
     * @param array $push
     * @return boolean $passed
     */
    protected function verifyPolicy(array $config, array $push)
    {
        // We are merging the individual message parts together to get the complete array of messages
        $messages = implode("\n\n", array_merge(
            // Hint: A lot of the following comments are "written negated" compared to the actual
            // statement for better readability.

            // Are there any forbidden actions we should check?
            !array_key_exists('forbidden', $config) ? [] : array_intersect_key(
                // remove the elements which aren't applicable
                $config['forbidden'],
                array_filter($push['context']['is'], function ($state) { return $state; })
            ),

            // Should the name be validated?
            !(array_key_exists('name', $config) && isset($push['context']['ref_name'])) ? [] : array_merge(
                // simply check if the ref name is on the forbidden list
                isset($config['name']['forbidden'][$push['context']['ref_name']]) ?
                    [$config['name']['forbidden'][$push['context']['ref_name']]] : [],

                // check if the forbidden patterns brings any messages
                !isset($config['name']['forbidden_patterns']) ? [] : array_keys(array_filter(
                    array_flip($config['name']['forbidden_patterns']),
                    function ($pattern) use ($push) { return preg_match($pattern, $push['context']['ref_name']); }
                )),

                // do pretty much the same for the require pattern -
                //  but add the message if the pattern wasn't matched
                !isset($config['name']['required_patterns']) ? [] : array_keys(array_filter(
                    array_flip($config['name']['required_patterns']),
                    function ($pattern) use ($push) { return !preg_match($pattern, $push['context']['ref_name']); }
                ))
            )
        ));

        $this->out($messages, 'error');
        return (trim($messages) == '');
    }

    /**
     * prints notications (messages) after the push.
     *
     * Are there any messages which should be printed after the push has been confirmed to be accepted?
     *
     * This method is used for some functional programming demonstration as well.
     *
     * @param array $config
     * @param array $push
     */
    protected function printMessages(array $config, array $push)
    {
        // Pretty much the same again, just more wrapped into itself.
        $this->out(implode(
            "\n\n",
            !array_key_exists('after_push_messages', $config) ? [] : array_keys(array_filter(
                array_flip($config['after_push_messages']),
                function ($action) use ($push) {
                    // ensure we are displaying only the right messages for the context.
                    return isset($push['context']['is'][$action]) && $push['context']['is'][$action];
                }
            ))
        ), 'good');
    }

    /**
     * loads the configuration from the config.
     *
     * @param string $filename
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function loadConfig($filename = '.gitpolicy.yml')
    {
        // parse the yml
        $yaml = new Parser();

        // check if the file exists
        if (!file_exists($filename)) {
            throw new \Exception($filename.' not found. Maybe you want to run the init command again?');
        }

        return $yaml->parse(file_get_contents($filename));
    }

    /**
     * Helps to set the environment for the application. Basically does these two things:.
     *
     *  * applies the input definition to the input and sets the result as property of the application.
     *  * prepares the outputinterface
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function prepEnvironment(InputInterface $input, OutputInterface $output)
    {
        $input->bind(new InputDefinition(array(
            new InputOption('local_ref', 'lref', InputOption::VALUE_REQUIRED, 'local ref'),
            new InputOption('local_sha', 'lsha', InputOption::VALUE_REQUIRED, 'local sha'),
            new InputOption('remote_ref', 'rref', InputOption::VALUE_REQUIRED, 'remote ref'),
            new InputOption('remote_sha', 'rsha', InputOption::VALUE_REQUIRED, 'remote sha'),
        )));

        $this->input = $input->getOptions();

        $output->getFormatter()->setStyle('error', new OutputFormatterStyle('white', 'red', array('bold')));
        $output->getFormatter()->setStyle('warning', new OutputFormatterStyle('black', 'yellow', array('bold')));
        $output->getFormatter()->setStyle('good', new OutputFormatterStyle('white', 'green', array('bold')));

        $this->output = $output;
    }

    /**
     * printing messages for lazy people like me.
     *
     * @param string $message
     * @param string $messageType
     */
    protected function out($message, $messageType = null)
    {
        // empty messages have no value for the user.
        if (trim($message) == '') {
            return;
        }

        // wrap message in tag to define the output
        if ($messageType != null) {
            $message = "<{$messageType}>".trim($message)."</{$messageType}>";
        }

        // yay! print it :)
        $this->output->writeln($message."\n");

        // we really shouldn't continue if an error happens
        if ($messageType == 'error') {
            $this->output->writeln('<error>Stopping :/</error>');
        }
    }
}
