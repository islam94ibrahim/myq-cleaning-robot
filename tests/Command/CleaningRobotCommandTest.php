<?php

namespace Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CleaningRobotCommandTest extends TestCase
{
    private $command;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->command = $this->createApplication()->find('cleaning_robot');

    }

    private function createApplication()
    {
        return require __DIR__ . '/../bootstrap.php';
    }

    public function testCommandReturnExpectedOutputForTestFileOne()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'source' => __DIR__ . '/../Files/test1.json',
            'result' => __DIR__ . '/../Files/result.json'
        ]);

        $this->assertFileExists(__DIR__ . '/../Files/result.json');

        $content = json_decode(file_get_contents(__DIR__ . '/../Files/result.json'), true);
        $this->assertEquals([
            "visited" => [["X" => 3, "Y" => 0], ["X" => 2, "Y" => 0], ["X" => 1, "Y" => 0]],
            "cleaned" => [["X" => 2, "Y" => 0], ["X" => 1, "Y" => 0]],
            "final" => ["X" => 2, "Y" => 0, "facing" => "N"],
            "battery" => 53
        ], $content);

        unlink(__DIR__ . '/../Files/result.json');
    }

    public function testCommandReturnExpectedOutputForTestFileTwo()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'source' => __DIR__ . '/../Files/test2.json',
            'result' => __DIR__ . '/../Files/result.json'
        ]);

        $this->assertFileExists(__DIR__ . '/../Files/result.json');

        $content = json_decode(file_get_contents(__DIR__ . '/../Files/result.json'), true);
        $this->assertEquals([
            "visited" => [["X" => 3, "Y" => 1], ["X" => 3, "Y" => 0], ["X" => 2, "Y" => 0]],
            "cleaned" => [["X" => 3, "Y" => 0], ["X" => 2, "Y" => 0]],
            "final" => ["X" => 3, "Y" => 0, "facing" => "N"],
            "battery" => 1063
        ], $content);

        unlink(__DIR__ . '/../Files/result.json');
    }
}
