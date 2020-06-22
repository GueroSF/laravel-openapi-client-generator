<?php

use Orchestra\Testbench\TestCase;

class GeneratePhpClientTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput();
    }

    protected function getEnvironmentSetUp($app): void {
        $app['config']->set('openapi-client-generator.apidoc_dir', ('./tests/api-docs'));
        $app['config']->set('openapi-client-generator.output_dir', '../openapi-test-client');
        $app['config']->set('openapi-client-generator.php_args.git_user_id', 'Baristanko');
        $app['config']->set('openapi-client-generator.php_args.git_repo_id', 'openapi-client-php-example');
        $app['config']->set('openapi-client-generator.php_args.params', [
            'apiPackage' => 'Api',
            'invokerPackage' => 'Baristanko\\OpenapiClientPHPExample',
            'modelPackage' => 'Dto',
            'packageName' => 'Openapi-Client-PHP-Example'
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            'Greensight\LaravelOpenapiClientGenerator\OpenapiClientGeneratorServiceProvider'
        ];
    }

    public function testPushAndPop()
    {
        $code = $this->artisan('openapi:generate-client-php');
        $this->assertSame($code, 0);
    }
}
