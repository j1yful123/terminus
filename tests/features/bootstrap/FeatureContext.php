<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode,
    Behat\Gherkin\Node\ScenarioNode;

/**
 * Features context.
 */
class FeatureContext extends BehatContext {
    public $cliroot = '';
    private $cassette_name;
    private $connection_info = [];
    private $output;

    /**
    * Initializes context. Sets directories for navigation.
    * 
    * @param $parameters [array] context parameters (set them up through behat.yml)
    */
    public function __construct(array $parameters) {
      $this->cliroot = dirname(dirname(__DIR__)) . '/..';
      $this->connection_info = $parameters;
    }

    /**
    * @BeforeScenario
    * Runs before each scenario
    * 
    * @param $event [ScenarioEvent]
    */
    public function before($event) {
      $this->setCassetteName($event);
    }

    /**
     * @When /^I am authenticating$/
     */
    public function iAmAuthenticating() {
      if(
        !isset($this->connection_info['username']) 
        || !isset($this->connection_info['password'])
      ) 
        throw new Exception("Check your configuration file to ensure proper configuration.");
      $this->iRun('terminus auth login ' . $this->connection_info['username'] 
        . ' --password=' . $this->connection_info['password']);
    }

    /**
    * @Given /^I am in directory "([^"]*)"$/
    * Changes the directory to given subdir of Terminus root directory
    * 
    * @param $dir [string]
    */
    public function iAmInDirectory($dir) {
      chdir($this->cliroot . $dir);
      return true;
    }

    /**
    * @Then /^I enter "([^"]*)"$/ 
    * 
    * @param $string [string]
    */
    public function iEnter($string) {
      $fh = fopen("php://stdin", 'w');
      fwrite($fh, "$string\n");
    }

    /**
    * @When /^I run "([^"]*)"$/
    * Runs command and saves output
    * 
    * @param $command [string]
    */
    public function iRun($command) {
      $terminus_cmd = sprintf('bin/terminus', $this->cliroot);
      $command = 'VCR_CASSETTE=' . $this->cassette_name 
        . ' ' . str_replace("terminus", $terminus_cmd, $command);
      if(isset($this->connection_info['vcr_mode'])) $command = 
        'VCR_MODE=' . $this->connection_info['vcr_mode'] . ' ' . $command;
      if(isset($this->connection_info['host'])) $command = 
        'TERMINUS_HOST=' . $this->connection_info['host'] . ' ' . $command;
      $this->output = shell_exec($command);
    }

    /**
    * @Then /^I should get:$/ 
    * 
    * @param $string [PyStringNode]
    */
    public function iShouldGet(PyStringNode $string) {
      if(!$this->checkResult((string)$string, $this->output))
        throw new Exception("Actual output:\n" . $this->output);
    }

    /**
    * @Then /^I should not get:$/ 
    * 
    * @param $string [PyStringNode]
    */
    public function iShouldNotGet(PyStringNode $string) {
      if($this->checkResult((string)$string, $this->output))
        throw new Exception("Actual output:\n" . $this->output);
    }

    /**
    * Checks the the haystack for the needle
    * 
    * @param $needle [string]
    * @param $haystack [string]
    * @return [boolean] true if $nededle was found in $haystack
    */
    private function checkResult($needle, $haystack) {
      return preg_match("#" . preg_quote($needle . "#s"), $haystack);
    }

    /**
    * Returns tags in easy-to-use array format.
    * 
    * @param $event [ScenarioEvent]
    * @return $tags [array] An array of strings corresponding to tags
    */
    private function getTags($event) {
      $unformatted_tags = $event->getScenario()->getTags();
      $tags = [];

      foreach($unformatted_tags as $tag) {
        $tag_elements = explode(' ', $tag);
        $index = null;
        if(count($tag_elements < 1)) $index = array_shift($tag_elements);
        if(count($tag_elements == 1)) $tag_elements = array_shift($tag_elements);
        $tags[$index] = $tag_elements;
      }

      return $tags;
    }

    /**
    * Sets $this->cassette_name and returns name of the cassette to be used.
    * 
    * @param $event [SuiteEvent]
    * @return [string] Of scneario name, lowercase, with underscores and suffix
    */
    private function setCassetteName($event) {
      $tags = $this->getTags($event);
      $this->cassette_name = $tags['vcr'];
      return $this->cassette_name;
    }
}