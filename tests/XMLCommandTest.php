<?php

namespace Gogilo\SmsParserCli\Console\Tests;

use Gogilo\SmsParserCli\Console\XMLCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class XMLCommandTest extends TestCase
{
    private CommandTester $commandTester;
    private string $outputFile;

    protected function setUp(): void
    {
        $this->outputFile = __DIR__ . '/../test_output.csv';

        // Clean up from previous tests
        if (file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }

        // Create a test SMS XML file
        $this->createTestXmlFile();

        $application = new Application();
        $application->add(new XMLCommand());

        $command = $application->find('xml');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }
        if (file_exists(__DIR__ . '/../sms-test.xml')) {
            unlink(__DIR__ . '/../sms-test.xml');
        }
    }

    private function createTestXmlFile(): void
    {
        $xmlContent = <<<XML
<?xml version='1.0' encoding='UTF-8' standalone='yes' ?>
<smses count="1">
    <sms protocol="0" address="MPESA" date="123456789" type="1" body="ABC123 Confirmed.You have received Ksh1,000.00 from JOHN DOE 254712345678 on 1/1/23 at 12:00 PM" />
    <sms protocol="0" address="MPESA" date="123456790" type="1" body="XYZ456 Confirmed.You have received Ksh2,500.00 from JANE DOE 254798765432 on 2/1/23 at 1:30 PM" />
    <sms protocol="0" address="OTHER" date="123456791" type="1" body="This should be ignored" />
</smses>
XML;

        file_put_contents(__DIR__ . '/../sms-test.xml', $xmlContent);
    }

    public function testCommandWithValidInput(): void
    {
        $command = $this->commandTester->getCommand();
        $command->setXmlFilePattern(__DIR__ . '/../sms-test.xml');

        $this->commandTester->execute([
            'sender_name' => 'JOHN DOE',
            '--output' => $this->outputFile,
        ]);

        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Processing sms-test.xml', $output);
        $this->assertStringContainsString('MATCHED TRANSACTION', $output);
        $this->assertStringContainsString('Ref: ABC123', $output);

        // Verify CSV output
        $this->assertFileExists($this->outputFile);
        $csvContent = file_get_contents($this->outputFile);
        $this->assertStringContainsString('ABC123', $csvContent);
        $this->assertStringContainsString('JOHN DOE', $csvContent);
    }

    public function testCommandWithNoMatches(): void
    {
        $command = $this->commandTester->getCommand();
        $command->setXmlFilePattern(__DIR__ . '/../sms-test.xml');

        $this->commandTester->execute([
            'sender_name' => 'NON EXISTENT',
            '--output' => $this->outputFile,
        ]);

        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Processing sms-test.xml', $output);
        $this->assertStringNotContainsString('MATCHED TRANSACTION', $output);
    }

    public function testCommandWithDebugMode(): void
    {
        $command = $this->commandTester->getCommand();
        $command->setXmlFilePattern(__DIR__ . '/../sms-test.xml');

        $this->commandTester->execute([
            'sender_name' => 'NON EXISTENT',
            '--output' => $this->outputFile,
            '--debug' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString("Name 'NON EXISTENT' not found in message", $output);
    }

    public function testCommandWithDuplicateDetection(): void
    {
        $command = $this->commandTester->getCommand();
        $command->setXmlFilePattern(__DIR__ . '/../sms-test.xml');

        // First run - should find the transaction
        $this->commandTester->execute([
            'sender_name' => 'JANE DOE',
            '--output' => $this->outputFile,
        ]);

        // Second run - should detect duplicate
        $this->commandTester->execute([
            'sender_name' => 'JANE DOE',
            '--output' => $this->outputFile,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Duplicate reference XYZ456 — Skipping', $output);
    }
}
