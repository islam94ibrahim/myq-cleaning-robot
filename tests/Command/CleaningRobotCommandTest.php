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

    public function testInvalidSourceFilePath()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'source' => __DIR__ . '/wrong_file.json',
            'result' => __DIR__ . '/result.json'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertEquals('Source path is not valid!', trim($output));
    }

    public function testInvalidResultFilePath()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'source' => __DIR__ . '/../Files/test_no_start_path.json',
            'result' => __DIR__ . '/wrong_directory/result.json'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertEquals('Result path is not valid!', trim($output));
    }

    public function testNoPathFromStart()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'source' => __DIR__ . '/../Files/test_no_start_path.json',
            'result' => __DIR__ . '/result.json'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertEquals('Source file content is not valid!', trim($output));
    }

    public function testOutOfBattery()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'source' => __DIR__ . '/../Files/test_out_of_battery.json',
            'result' => __DIR__ . '/../Files/result.json'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Battery fully consumed', $output);

        unlink(__DIR__ . '/../Files/result.json');
    }

    public function testAllBackOffSequencesTriggered()
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute([
            'source' => __DIR__ . '/../Files/test_stuck.json',
            'result' => __DIR__ . '/../Files/result.json'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('["TR","A","TL"]', $output);
        $this->assertStringContainsString('["TR","A","TR"]', $output);
        $this->assertStringContainsString('["TR","B","TR","A"]', $output);
        $this->assertStringContainsString('["TL","TL","A"]', $output);

        unlink(__DIR__ . '/../Files/result.json');
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
