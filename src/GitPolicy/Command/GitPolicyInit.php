<?php

/*
 * initializes gitpolicy in a repo by adding the git hook as well as the default .gitpolicy.yml
 */

namespace GitPolicy\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class GitPolicyInit extends Command
{
    /**
     * configure the command.
     */
    protected function configure()
    {
        // define the command
        $this
            ->setName('init')
            ->setDescription('Initializes GitPolicy for a git repository.')
            ->setHelp(<<<EOT
Creates the following

 * git hook to capture the pushes and
 * default .gitconfig.yml

See https://github.com/Spekulatius/GitPolicy for more information.
EOT
);
    }

    /**
     * runs all related init steps for this repo.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // prep some colors for the output
        $output->getFormatter()->setStyle('error', new OutputFormatterStyle('white', 'red', array('bold')));
        $output->getFormatter()->setStyle('warning', new OutputFormatterStyle('black', 'yellow', array('bold')));
        $output->getFormatter()->setStyle('good', new OutputFormatterStyle('white', 'green', array('bold')));

        // start print out
        $output->writeln("\n<good>GitPolicy initialization</good>");

        // check if this is a git repo
        exec('git status 2>&1', $return, $returnCode);
        if ($returnCode > 0) {
            $output->writeln("<error>This doesn't apprear to be a git repo.</error>");
            $output->writeln("\n<good>Stopping execution :(</good>\n");
            exit(0);
        }

        // check if git hook is already installed.
        $output->writeln("\nGit hook");
        if (file_exists('.git/hooks/pre-push')) {
            // quick and dirty check if this is already the GitPolicy hook
            if (md5(file_get_contents('templates/pre-push')) == md5(file_get_contents('.git/hooks/pre-push'))) {
                $output->writeln('<good>Is already in place :)</good>');
            } else {
                $output->writeln(
                    '<warning>This repository already has a "pre-push" git hook.'.
                    'Please ensure manually it works with GitPolicy. Continuing...</warning>'
                );
            }
        } else {
            $output->writeln(
                '<good>Adding the git hook :)</good>'
            );

            // copy over the file and attempt to adjust the permissions
            copy(realpath('templates/pre-push'), '.git/hooks/pre-push');
            chmod('.git/hooks/pre-push', 0755);
        }

        // check if there is already a configuration file
        $output->writeln("\n.gitpolicy.yml file");
        if (file_exists('.gitpolicy.yml')) {
            $output->writeln(
                '<warning>.gitpolicy.yml already exists. Won\'t copy default config in. Continuing...</warning>'
            );
        } else {
            copy(realpath('templates/.gitpolicy.yml'), '.gitpolicy.yml');

            $output->writeln(
                '<good>We copied an example of the .gitpolicy.yml into your folder. '.
                'Have a look and adjust it to your needs :)</good>'
            );
        }

        $output->writeln("\n<good>Done</good>\n");
    }
}
