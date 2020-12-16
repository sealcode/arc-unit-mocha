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
    private $dockerRoot;

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

        $this->dockerRoot = $config->getConfigFromAnySource('unit.mocha.dockerRoot', $this->projectRoot);

        $this->setEnableCoverage(true);
    }

    public function run() {
        $this->loadEnvironment();

        // Temporary files for holding report output
        $xunit_tmp = "./.xunit";
        $cover_xml_path = './coverage/clover.xml';

        // Build and run the unit test command
        $future = $this->buildTestFuture();
        $future->setCWD($this->projectRoot);

        list($stdout, $stderr) = $future->resolvex();

        // Parse and return the xunit output
        $this->parser = new ArcanistXUnitTestResultParser();
        $results = $this->parseTestResults($xunit_tmp, $cover_xml_path);

        return $results;
    }

    protected function buildTestFuture() {
        return new ExecFuture('npm run test-reports');
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
            $absolute_path = str_replace($this->dockerRoot, $this->projectRoot, $file->getAttribute('path'));
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
