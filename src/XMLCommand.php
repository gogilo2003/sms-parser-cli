<?php

namespace Gogilo\SmsParserCli\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class XMLCommand extends Command
{
    private string $xmlFilePattern = 'sms-*.xml';

    protected function configure()
    {
        $this
            ->setName('xml')
            ->setDescription('A CLI tool to extract M-Pesa transactions from SMS XML backups')
            ->addArgument(
                'sender_name',
                InputArgument::REQUIRED,
                'The sender name to filter transactions'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output CSV file',
                'mpesa_transactions.csv'
            )
            ->addOption(
                'debug',
                'd',
                InputOption::VALUE_NONE,
                'Enable debug mode'
            );
    }

    public function setXmlFilePattern(string $pattern): void
    {
        $this->xmlFilePattern = $pattern;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $senderName = trim($input->getArgument('sender_name'));
        $outputFile = $input->getOption('output');
        $debugMode = $input->getOption('debug');

        $pattern = '/
            ^([A-Z0-9]{10,})\s+                        # Reference code at the start
            Confirmed\.You\s+have\s+received\s+Ksh([\d,]+\.\d{2})\s+
            from\s+(.+?)\s+                            # Sender name
            (\d{10,12})\s+                             # Sender phone
            on\s+(\d{1,2}\/\d{1,2}\/\d{2})\s+at\s+(\d{1,2}:\d{2}\s[AP]M)
        /ix';

        // Load existing references if file exists
        $seenReferences = [];
        if (file_exists($outputFile)) {
            if (($handle = fopen($outputFile, 'r')) !== false) {
                fgetcsv($handle); // Skip header
                while (($row = fgetcsv($handle)) !== false) {
                    $seenReferences[$row[4]] = true; // Reference is column 4
                }
                fclose($handle);
            }
        }

        $csvHandle = fopen($outputFile, 'a'); // Use append mode
        if (filesize($outputFile) === 0) {
            fputcsv($csvHandle, ['Date', 'Mpesa Reference', 'From Name', 'From Phone', 'Amount']);
        }

        $totalMatches = 0;
        $totalDuplicates = 0;

        foreach (glob($this->xmlFilePattern) as $filename) {
            $output->writeln("\n<fg=blue>Processing $filename</>");

            $xml = simplexml_load_file($filename);
            if (!$xml) {
                $output->writeln("<error>Invalid XML in $filename</error>");
                continue;
            }

            foreach ($xml->sms as $sms) {
                if ((string)$sms['address'] !== 'MPESA' || (int)$sms['type'] !== 1) {
                    continue;
                }

                $body = (string)$sms['body'];
                if (stripos($body, $senderName) === false) {
                    if ($debugMode) {
                        $output->writeln("<comment>Name '$senderName' not found in message:</comment>");
                        $output->writeln("  " . substr($body, 0, 80) . "...");
                    }
                    continue;
                }

                $output->writeln("  <fg=green>Potential match found!</>");

                if (preg_match($pattern, $body, $matches)) {
                    $reference = $matches[1];

                    if (isset($seenReferences[$reference])) {
                        $totalDuplicates++;
                        $output->writeln("  <comment>Duplicate reference $reference — Skipping</comment>");
                        continue;
                    }

                    $amount = $matches[2];
                    $fromName = trim($matches[3]);
                    $fromPhone = $matches[4];
                    $date = $matches[5] . ' at ' . $matches[6];

                    $output->writeln("  <fg=green;options=bold>MATCHED TRANSACTION:</>");
                    $output->writeln("    Ref: $reference");
                    $output->writeln("    From: $fromName");
                    $output->writeln("    Phone: $fromPhone");
                    $output->writeln("    Amount: $amount");
                    $output->writeln("    Date: $date");

                    fputcsv($csvHandle, [$date, $reference, $fromName, $fromPhone, $amount]);
                    $seenReferences[$reference] = true;
                    $totalMatches++;
                } else {
                    $output->writeln("  <fg=red>PATTERN DIDN'T MATCH:</>");
                    $output->writeln("  " . substr($body, 0, 120) . "...");
                }
            }
        }

        fclose($csvHandle);

        $output->writeln("\n<fg=magenta;options=bold>RESULTS:</>");
        $output->writeln("- Found $totalMatches new transactions");
        $output->writeln("- Skipped $totalDuplicates duplicate references");
        $output->writeln("- Output saved to $outputFile");

        return Command::SUCCESS;
    }
}
