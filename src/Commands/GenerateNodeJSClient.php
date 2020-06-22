<?php

namespace Greensight\LaravelOpenapiClientGenerator\Commands;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

use Greensight\LaravelOpenapiClientGenerator\Core\Patchers\NodeJSEnumPatcher;

class GenerateNodeJSClient extends Command {
    /**
     * @var string
     */
    protected $signature = 'openapi:generate-client-nodejs';

    /**
     * @var string
     */
    protected $description = 'Generate nodejs http client from openapi spec files by OpenApi Generator';

    /**
     * @var string
     */
    private $apidocDir;

    /**
     * @var string
     */
    private $outputDir;

    /**
     * @var array
     */
    private $params;

    public function __construct()
    {
        parent::__construct();

        $this->apidocDir = config('openapi-client-generator.apidoc_dir');
        $this->outputDir = config('openapi-client-generator.output_dir') . '-js';
        $this->params = config('openapi-client-generator.nodejs_args.params');
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->generateClientPackage();
        $this->patchEnums();
    }

    private function generateClientPackage(): void
    {
        $bin = 'npx @openapitools/openapi-generator-cli';
        $command = "$bin generate -i $this->apidocDir/index.yaml -g typescript-axios -o $this->outputDir";

        $paramsArgument = $this->getParamsArgument();

        if (Str::length($paramsArgument) > 0) {
            $command .= " -p \"$paramsArgument\"";
        }

        $this->info("Generate client by command: $command");

        shell_exec($command);
    }

    private function getParamsArgument(): string
    {
        return collect($this->params)
            ->map(function ($value, $name) {
                $stringValue = var_export($value, true);
                return "$name=$stringValue";
            })
            ->join(',');
    }


    private function patchEnums(): void
    {
        $files = new RegexIterator(
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $this->outputDir,
                    FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS
                )
            ),
            '/-enum\.ts$/i',
            RegexIterator::MATCH
        );

        foreach ($files as $file) {
            $this->info("Patch enum: " . $file->getPathName());

            $patcher = new NodeJSEnumPatcher($file, $this->apidocDir);
            $patcher->patch();
        }
    }
}
