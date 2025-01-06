<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class СommissionsCommand extends Command
{
    public const BIN_URL = 'https://lookup.binlist.net/';
    public const RATE_URL = 'https://api.exchangeratesapi.io/latest';
    public const COUNTRY = 'AT,BE,BG,CY,GR,CZ,DE,DK,EE,ES,FI,FR,HR,HU,IE,IT,LT,LU,LV,MT,NL,PO,PT,RO,SE,SI,SK';

    protected function configure(): void
    {
        $this->setName('commissions')
            ->setDescription('This command calculate commissions for already made transactions.')
            ->setHelp('Run this command to calculate commissions for already made transactions.')
            ->addArgument('url', InputArgument::REQUIRED, 'URL of JSON data for calculations.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('URL of JSON data for calculations: ' . $input->getArgument('url'));

        if (self::urlExists($input->getArgument('url'))) {
            $inputData = file($input->getArgument('url'));

            foreach($inputData as $row){
                if (empty($row)) break;

                //get row data from file
                $data = json_decode($row);

                //get bin data
                if (!self::urlExists(self::BIN_URL .$data->bin)){
                    $output->writeln('BIN server URL '.self::BIN_URL .$data->bin.' is not accessible!');
                    return Command::FAILURE;
                }

                $binResults = file_get_contents(self::BIN_URL .$data->bin);
                $r = json_decode($binResults);
                $isEu = self::isEu($r->country->alpha2);

                //get rate data
                if (!self::urlExists(self::RATE_URL)){
                    $output->writeln('RATE server URL '.self::RATE_URL.' is not accessible!');
                    return Command::FAILURE;
                }

                $rate = @json_decode(file_get_contents(self::RATE_URL), true)['rates'][$data->currency];
                if ($data->currency == 'EUR' || $rate == 0) {
                    $amountFixed = $data->amount;
                }
                else {
                    $amountFixed = $data->amount / $rate;
                }

                //calculate commissions
                $commissions = $amountFixed * ($isEu ? 0.01 : 0.02);

                //show it in console
                $output->writeln($commissions);
            }

            $output->writeln('Data file found.');
            return Command::SUCCESS;

        } else {
            $output->writeln('This is wrong URL for data file.');
            return Command::FAILURE;
        }
    }

    protected function urlExists($url): bool
    {
        $headers = get_headers($url);
        return (bool)stripos($headers[0], "200 OK");
    }

    protected function isEu($c) : bool
    {
        if(preg_match('/'.$c.'/i',self::COUNTRY)) {
            return true;
        }
        return false;
    }
}