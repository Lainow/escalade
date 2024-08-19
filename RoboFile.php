<?php

class RoboFile extends \Robo\Tasks
{
    /**
     * Pull translations from Transifex.
     *
     * @param int $percent Minimum percentage of translation completion to pull.
     * @return $this|null Returns $this on success, null on failure.
     */
    public function localesPull($percent = 70)
    {
        $txPath = getenv('HOME') . '/.local/bin/tx';
        if (!file_exists($txPath)) {
            $this->say("Error: tx executable not found at $txPath");
            return null;
        }

        $command = "$txPath pull -a --minimum-perc=$percent";
        $this->say("Executing command: $command");

        $result = $this->taskExec($command)
                       ->dir(getcwd())
                       ->printOutput(true)
                       ->run();

        if (!$result->wasSuccessful()) {
            $this->say('Failed to pull translations');
            $this->say($result->getMessage());
            $this->say($result->getOutputData());
            return null;
        } else {
            $this->say('Translations pulled successfully');
            return $this;
        }
    }

    /**
     * Generate .mo files from .po files after pulling translations.
     *
     * @param int $percent Minimum percentage of translation completion to pull.
     * @return $this
     */
    public function localesGenerate($percent = 70)
    {
        $pullResult = $this->localesPull($percent);
        if ($pullResult !== null) {
            $pullResult->localesMo();
        }
        return $this;
    }

    /**
     * Compile .po files into .mo files.
     *
     * @return $this
     */
    public function localesMo()
    {
        $po_files = preg_grep('/\.po$/', scandir('./locales'));
        foreach ($po_files as $po_file) {
            $mo_file = preg_replace('/\.po$/', '.mo', $po_file);
            echo("Processing {$po_file}\n");
            passthru("cd ./locales && msgfmt -f -o {$mo_file} {$po_file}", $exit_code);
            if ($exit_code > 0) {
                exit($exit_code);
            }
        }

        return $this;
    }

    /**
     * Extract and push source locales to Transifex.
     *
     * @return $this
     */
    public function localesSend() {
        $this->localesExtract()->localesPush();
        return $this;
    }

    /**
     * Extract translation templates.
     *
     * @return $this
     */
    public function localesExtract() {
        $this->_exec('tools/extract_template.sh');
        return $this;
    }

    /**
     * Push source locales to Transifex.
     *
     * @return $this
     * @throws RuntimeException if the push to Transifex fails.
     */
    public function localesPush() {
        $success = $this->taskExec('tx')
            ->arg('push')
            ->arg('-s')
            ->run();
        if ($success->getExitCode() != 0) {
            throw new RuntimeException("failed to send source locales to transifex");
        }
        return $this;
    }
}
