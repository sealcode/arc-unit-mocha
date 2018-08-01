<?php

final class MochaEngine extends ArcanistUnitTestEngine {

    private $projectRoot;
    private $parser;

    private $mochaBin;
    private $_mochaBin;
    private $istanbulBin;
    private $coverReportDir;
    private $coverExcludes;
    private $testIncludes;

    /**
     * Determine which executables and test paths to use.
     *
     * Ensure that all of the required binaries are available for the
     * tests to run successfully.
     */
    protected function loadEnvironment() {
        $this->projectRoot = $this->getWorkingCopy()->getProjectRoot();

        // Get config options
        $config = $this->getConfigurationManager();

        $this->coverExcludes = $config->getConfigFromAnySource(
            'unit.mocha.coverage.exclude');

        $this->testIncludes = $config->getConfigFromAnySource(
            'unit.mocha.test.include',
            '');
    }

    public function run() {
        $this->loadEnvironment();

        // Temporary files for holding report output
        $xunit_tmp = "./.xunit";
        $cover_xml_path = './coverage/clover.xml';

        // Build and run the unit test command
        $future = $this->buildTestFuture();
        $future->setCWD($this->projectRoot);

        try {
            list($stdout, $stderr) = $future->resolvex();
        } catch (CommandException $exc) {
            if ($exc->getError() > 1) {
                // mocha returns 1 if tests are failing
                throw $exc;
            }
        }

        if ($this->getEnableCoverage() !== false) {
            // Remove coverage report if it already exists
            if (file_exists($cover_xml_path)) {
                if(!unlink($cover_xml_path)) {
                    throw new Exception("Couldn't delete old coverage report '".$cover_xml_path."'");
                }
            }

            // Build and run the coverage command
            $future = $this->buildCoverFuture();
            $future->setCWD($this->projectRoot);
            $future->resolvex();
        }

        // Parse and return the xunit output
        $this->parser = new ArcanistXUnitTestResultParser();
        $results = $this->parseTestResults($xunit_tmp, $cover_xml_path);

        return $results;
    }

    protected function buildTestFuture() {
        return new ExecFuture('make --quiet xunit');
    }

    protected function buildCoverFuture() {
        return new ExecFuture('make coverage');
    }

    protected function parseTestResults($xunit_tmp, $cover_xml_path) {
        $results = $this->parser->parseTestResults(Filesystem::readFile($xunit_tmp));

        if ($this->getEnableCoverage() !== false) {
            $coverage_report = $this->readCoverage($cover_xml_path);
            foreach($results as $result) {
                $result->setCoverage($coverage_report);
            }
        }

        return $results;
    }

    public function readCoverage($path) {
        $coverage_data = Filesystem::readFile($path);
        if (empty($coverage_data)) {
            return array();
        }

        $coverage_dom = new DOMDocument();
        $coverage_dom->loadXML($coverage_data);

        $reports = array();
        $classes = $coverage_dom->getElementsByTagName('class');

        $files = $coverage_dom->getElementsByTagName('file');
        foreach ($files as $file) {
            $absolute_path = $file->getAttribute('path');
            $relative_path = str_replace($this->projectRoot.'/', '', $absolute_path);

            $line_count = count(file($absolute_path));

            // Mark unused lines as N, covered lines as C, uncovered as U
            $coverage = '';
            $start_line = 1;
            $lines = $file->getElementsByTagName('line');
            for ($i = 0; $i < $lines->length; $i++) {
                $line = $lines->item($i);
                $line_number = (int)$line->getAttribute('num');
                $line_hits = (int)$line->getAttribute('count');

                $next_line = $line_number;
                for ($start_line; $start_line < $next_line; $start_line++) {
                    $coverage .= 'N';
                }

                if ($line_hits > 0) {
                    $coverage .= 'C';
                } else {
                    $coverage .= 'U';
                }

                $start_line++;
            }

            while ($start_line <= $line_count) {
                $coverage .= 'N';
                $start_line++;
            }

            $reports[$relative_path] = $coverage;
        }

        return $reports;
    }

}
