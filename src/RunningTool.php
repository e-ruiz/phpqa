<?php

namespace Edge\QA;

class RunningTool
{
    private $tool;
    public $binary;
    private $internalClasses;
    private $optionSeparator;
    private $outputMode;

    private $xmlFiles;
    private $errorsXPath;
    public $errorsType;
    private $allowedErrorsCount;

    public $htmlReport;
    public $userReports = [];
    /** @var \Symfony\Component\Process\Process */
    public $process;

    public function __construct($tool, array $toolConfig)
    {
        $config = $toolConfig + [
            'binary' => $tool,
            'optionSeparator' => '=',
            'xml' => [],
            'errorsXPath' => '',
            'allowedErrorsCount' => null,
            'outputMode' => OutputMode::HANDLED_BY_TOOL,
            'internalClass' => null,
        ];
        $this->tool = $tool;
        $this->binary = $config['binary'];
        $this->internalClasses = $config['internalClass'] ? ((array) $config['internalClass']) : array();
        $this->optionSeparator = $config['optionSeparator'];
        $this->xmlFiles = $config['xml'];
        $this->errorsXPath = is_array($config['errorsXPath'])
            ? $config['errorsXPath'] : [$this->errorsXPath => $config['errorsXPath']];
        $this->allowedErrorsCount = $config['allowedErrorsCount'];
        $this->outputMode = $config['outputMode'];
    }

    public function isInstalled()
    {
        if (!$this->internalClasses) {
            return true;
        }
        foreach ($this->internalClasses as $class) {
            if (class_exists($class)) {
                return true;
            }
        }
        return false;
    }

    public function hasOutput($outputMode)
    {
        return $this->outputMode == $outputMode;
    }

    public function buildOption($arg, $value)
    {
        if ($value || $value === 0) {
            return "--{$arg}{$this->optionSeparator}{$value}";
        } else {
            return "--{$arg}";
        }
    }

    public function getAllowedErrorsCount()
    {
        return $this->allowedErrorsCount;
    }

    public function analyzeResult($hasNoOutput = false)
    {
        $xpath = $this->errorsXPath[$this->errorsType];

        if ($hasNoOutput ||
            $this->hasOutput(OutputMode::RAW_CONSOLE_OUTPUT) ||
            $this->hasOutput(OutputMode::CUSTOM_OUTPUT_AND_EXIT_CODE)
        ) {
            return $this->evaluteErrorsCount($this->process->getExitCode() ? 1 : 0);
        } elseif (!$xpath) {
            return [true, ''];
        } elseif (!file_exists($this->getMainXml())) {
            return [false, 0];
        }

        $xml = simplexml_load_file($this->getMainXml());
        $errorsCount = count($xml->xpath($xpath));
        return $this->evaluteErrorsCount($errorsCount);
    }

    private function evaluteErrorsCount($errorsCount)
    {
        $isOk = $errorsCount <= $this->allowedErrorsCount || $this->areErrorsIgnored();
        return [$isOk, $errorsCount];
    }

    private function areErrorsIgnored()
    {
        return !is_numeric($this->allowedErrorsCount);
    }

    public function getXmlFiles()
    {
        return $this->xmlFiles;
    }

    public function getEscapedXmlFile()
    {
        return escapePath($this->getMainXml());
    }

    private function getMainXml()
    {
        return reset($this->xmlFiles);
    }

    public function getHtmlRootReports()
    {
        $reports = [];
        foreach ($this->userReports as $report => $file) {
            $reports[] = [
                'id' => "{$this}-" . str_replace(['.', '/', '\\'], '-', $report),
                'name' => $report,
                'file' => $file,
            ];
        }
        return $reports;
    }

    public function __toString()
    {
        return $this->tool;
    }
}
