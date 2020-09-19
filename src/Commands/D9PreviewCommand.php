<?php
/**
 * This command will manage secrets on a Pantheon site.
 *
 * See README.md for usage information.
 */

namespace Pantheon\TerminusD9Preview\Commands;

use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Symfony\Component\Filesystem\Filesystem;
use Consolidation\AnnotatedCommand\AnnotationData;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Manage secrets on a Pantheon instance
 */
class D9PreviewCommand extends TerminusCommand implements SiteAwareInterface
{
    use SiteAwareTrait;

    /**
     * Object constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Preview the dev environment on d9 by creating a "d9" multidev
     *
     * @command preview:d9
     */
    public function preview()
    {
        // Make sure we are on the master branch
        $branch = exec('git rev-parse --abbrev-ref HEAD');
        if ($branch != 'master') {
            $this->exec('git checkout master');
        }

        $siteName = exec('terminus site:info --field=name');

        // Get a list of all of the commits that are update commits.
        $commitList = $this->getUpdateCommits();

        // Check to see if this site is currently on some 8.9.x version.
        $firstVersion = reset($commitList);
        if (substr($firstVersion, 0, 4) != '8.9.') {
            throw new \Exception("This command only works on sites that are running Drupal version 8.9.0 or later, but are not yet running Drupal 9.0.0 or later. This site appears to be on version $firstVersion.");
        }

        // Figure out which commits to revert
        $commitsToRevert = $this->selectBackTo($commitList, '8.9.0');

        // Check out "d9" multidev branch (reset to HEAD of master).
        $this->exec('git checkout -B preview-d9 master');

        // Revert the commits we selected above
        $this->revertAll($commitsToRevert);

        // Merge in d9 branch from drops-8
        $this->exec('git pull git@github.com:pantheon-systems/drops-8.git 9.x');

        // Check to see if there is a multidev for the preview-d9 branch.
        $previewMultidevExists = $this->checkMultidevExists();

        // Back to the master branch so that dev environment will be default
        // target for the Terminus commands that follow.
        $this->exec('git checkout master');

        // Either update the existing multidev, or create a new one as needed.
        if (!$previewMultidevExists) {
            $this->exec('terminus multidev:create preview-d9 --no-files --no-db');
        }

        // Force push preview-d9 branch
        $this->exec('git push --force origin preview-d9');

        // AVOID RACE CONDITION: We need to make sure that the git operation
        // finishes before the database / file clone does so that the
        // cc and updatedb will work correctly.
        $this->exec("terminus build:workflow:wait $siteName.preview-d9");

        // Copy database and files, clear the cache and run updatedb
        $this->exec('terminus env:clone-content preview-d9 --cc --updatedb --yes');
    }

    protected function getUpdateCommits()
    {
        // Get log lines that are by Pantheon Automation and include 'Update to Drupal' in the commit message.
        exec('git log --pretty=format:"%h %s" --author=bot@getpantheon.com | grep "Update to Drupal"', $logOutput, $status);

        if ($status != 0) {
            throw new \Exception('Could not fetch git log');
        }

        // Extract sha and version from the commit lines.
        $commitList = [];
        foreach ($logOutput as $line) {
            if (preg_match('/([0-9a-f]*) Update to Drupal ([0-9.]*)/', $line, $matches)) {
                $sha = $matches[1];
                $version = rtrim($matches[2], '.');
                $commitList["$sha"] = $version;
            }
        }
        return $commitList;
    }

    protected function selectBackTo($commitList, $limit)
    {
        $selected = [];

        // Revert Drupal releases from the most recent 8.9.x through 8.9.1, inclusive.
        foreach ($commitList as $sha => $version) {
            if ($version == $limit) {
                return $selected;
            }
            $selected[$sha] = $version;
        }

        throw new \Exception("Commit list did not contain version $limit, which must exist for this tool to work.");
    }

    protected function revertAll($commitsToRevert)
    {
        foreach ($commitsToRevert as $sha => $version) {
            $this->exec("git revert --no-edit $sha");
        }
    }

    protected function checkMultidevExists()
    {
        exec('terminus env:info', $output, $status);

        return $status == 0;
    }

    protected function exec($cmd)
    {
        $this->log()->notice('>> {cmd}', ['cmd' => $cmd]);

        exec($cmd, $output, $status);
        $output = implode("\n", $output);
        if ($status != 0) {
            throw new \Exception('exec "' . $cmd . '"" failed: ' . $output);
        }

        return $output;
    }
}
