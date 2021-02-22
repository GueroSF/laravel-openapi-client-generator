<?php

namespace Greensight\LaravelOpenapiClientGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

abstract class GenerateClient extends Command
{
    /**
     * @var string
     * Client name: js or php, must be set in child classes
     */
    protected $client;

    /**
     * @var string
     * Generator name, one of valid openapi generators names
     */
    protected $generator;

    /**
     * @var string
     */
    protected $apidocDir;

    /**
     * @var string
     */
    protected $outputDir;

    /**
     * @var string
     */
    protected $gitUser;

    /**
     * @var string
     */
    protected $gitRepo;

    /**
     * @var string
     */
    protected $gitHost;

    /**
     * @var array
     */
    protected $params;

    /**
     * @var string
     */
    protected $templateDir;

    /**
     * @var array
     */
    protected $ignoredFiles;

    public function __construct()
    {
        parent::__construct();

        $this->apidocDir = config('openapi-client-generator.apidoc_dir');
        $this->outputDir = config('openapi-client-generator.output_dir_template') . "-$this->client";

        $this->gitUser = config('openapi-client-generator.git_user', '');
        $this->gitRepo = config('openapi-client-generator.git_repo_template', '') . "-$this->client";
        $this->gitHost = config('openapi-client-generator.git_host', '');

        $this->params = config("openapi-client-generator.{$this->client}_args.params");
        $this->templateDir = config("openapi-client-generator.{$this->client}_args.template_dir", '');
        $this->ignoredFiles = config("openapi-client-generator.{$this->client}_args.files_for_ignore", '');
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->recursiveClearDirectory($this->outputDir);
        $this->generateClientPackage();
        $this->patchClientPackage();
        $this->copyLicenseToClientPackage();
    }

    protected abstract function patchClientPackage(): void;

    private function generateClientPackage(): void
    {
        $bin = 'npx @openapitools/openapi-generator-cli';
        $i = escapeshellarg($this->apidocDir . DIRECTORY_SEPARATOR . "index.yaml");
        $g = escapeshellarg($this->generator);
        $o = escapeshellarg($this->outputDir);
        $command = "$bin generate -i $i -g $g -o $o " . $this->getGeneratorArguments();

        $this->info("Generating $this->client client by command: $command");

        shell_exec($command);
    }

    private function getGeneratorArguments(): string
    {
        $arguments = '';

        if (Str::length($this->gitUser) > 0) {
            $arguments .= " --git-user-id " . escapeshellarg($this->gitUser);
        }

        if (Str::length($this->gitRepo) > 0) {
            $arguments .= " --git-repo-id " . escapeshellarg($this->gitRepo);
        }

        if (Str::length($this->gitHost) > 0) {
            $arguments .= " --git-host " . escapeshellarg($this->gitHost);
        }

        if (Str::length($this->templateDir) > 0) {
            $arguments .= " -t " . escapeshellarg($this->templateDir);
        }

        $additionalParams = $this->getAdditionalParamsArgument();

        if (Str::length($additionalParams) > 0) {
            $arguments .= " -p " . escapeshellarg($additionalParams);
        }

        return $arguments;
    }

    private function getAdditionalParamsArgument(): string
    {
        return collect($this->params)
            ->map(function ($value, $name) {
                $escapedValue = PHP_OS_FAMILY !== 'Windows' ? str_replace("\\", "\\\\", $value) : $value;
                return "$name=$escapedValue";
            })
            ->join(',');
    }

    private function copyLicenseToClientPackage(): void
    {
        $source = $this->templatePath('LICENSE-template.md');
        $dest = $this->outputDir . DIRECTORY_SEPARATOR . 'LICENSE.md';
        if (!file_exists($dest)) {
            copy($source, $dest);
            $this->info("Template LICENSE.md copied to package");
        }
    }

    protected function templatePath(string $path): string
    {
        return __DIR__ . '/../../templates/' . ltrim($path, '/');
    }

    /**
     * Очистка содержимого директории.
     * @param $dir
     */
    private function recursiveClearDirectory($dir)
    {
        foreach (glob($dir . '/*') as $file) {
            if (!in_array(str_replace($this->outputDir . "/", "", $file), $this->ignoredFiles)) {
                if (is_dir($file)) {
                    $this->recursiveClearDirectory($file);
                } else {
                    unlink($file);
                }
            }
        }
        if ($dir != $this->outputDir) {
            rmdir($dir);
        }
    }
}
